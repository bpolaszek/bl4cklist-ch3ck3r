<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes;

use BenTools\Bl4cklistCh3ck3r\GSB\Model\Hash;
use BenTools\SimpleDBAL\Contract\AdapterInterface;
use BenTools\SimpleDBAL\Model\Adapter\Mysqli\MysqliAdapter;
use function BenTools\Where\delete;
use function BenTools\Where\field;
use function BenTools\Where\insert;
use function BenTools\Where\select;
use function BenTools\Where\update;
use Symfony\Component\Cache\Adapter\PdoAdapter;

class DatabaseHashStorage implements HashStorageInterface
{
    /**
     * @var AdapterInterface
     */
    private $connection;

    /**
     * @var string
     */
    private $prefix;

    /**
     * @var int
     */
    private $buffer;

    private $prefixSizes = [];

    /**
     * DatabaseHashStorage constructor.
     * @param AdapterInterface|MysqliAdapter|PdoAdapter $connection
     * @param string                                    $prefix
     * @param int                                       $buffer
     */
    public function __construct(AdapterInterface $connection, string $prefix, int $buffer = 20000)
    {
        $this->connection = $connection;
        $this->prefix = $prefix;
        $this->buffer = $buffer;
    }

    /**
     * @inheritDoc
     */
    public function getPrefixSizes(string $threatType, string $threatEntryType, string $platformType): array
    {
        if (!$this->tableExists($threatType, $threatEntryType, $platformType)) {
            return [];
        }

        if (isset($this->prefixSizes[$threatType][$threatEntryType][$platformType])) {
            return $this->prefixSizes[$threatType][$threatEntryType][$platformType];
        }

        $select = select('prefixSize')->distinct()->from($this->getTableName($threatType, $threatEntryType, $platformType));
        $prefixSizes = $this->connection->execute((string) $select, $select->getValues())->asList();
        usort($prefixSizes, function ($a, $b) {
            return $b <=> $a;
        });

        return $this->prefixSizes[$threatType][$threatEntryType][$platformType] = $prefixSizes;
    }

    /**
     * @inheritDoc
     */
    public function clearHashes(string $threatType, string $threatEntryType, string $platformType): void
    {
        if (!$this->tableExists($threatType, $threatEntryType, $platformType)) {
            return;
        }
        $query = delete()->from($this->getTableName($threatType, $threatEntryType, $platformType));
        $this->connection->execute((string) $query);
        unset($this->prefixSizes[$threatType][$threatEntryType][$platformType]);
    }


    /**
     * @inheritDoc
     */
    public function getHashes(string $threatType, string $threatEntryType, string $platformType): iterable
    {
        foreach ($this->getRawHashes($threatType, $threatEntryType, $platformType) as $rawHash) {
            yield Hash::fromSha256($rawHash);
        }
    }

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return iterable
     */
    private function getRawHashes(string $threatType, string $threatEntryType, string $platformType): iterable
    {
        if (!$this->tableExists($threatType, $threatEntryType, $platformType)) {
            return [];
        }

        $select = select('hashSha256')->from($this->getTableName($threatType, $threatEntryType, $platformType))
            ->orderBy('hashIndex ASC');
        $result = $this->connection->execute((string) $select, $select->getValues());
        foreach ($result as $row) {
            yield $row['hashSha256'];
        }
    }

    /**
     * @inheritDoc
     */
    public function getCheckSum(string $threatType, string $threatEntryType, string $platformType): Hash
    {
        $megaHash = '';
        /** @var Hash[] $hashes */
        $hashes = $this->getHashes($threatType, $threatEntryType, $platformType);
        foreach ($hashes as $hash) {
            $megaHash .= $hash->toSha256();
        }
        return Hash::fromSha256($megaHash)->getChecksum();
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }


    /**
     * @inheritDoc
     */
    public function storeHashes(string $threatType, string $threatEntryType, string $platformType, array $additions, array $removals): void
    {
        $table = $this->getTableName($threatType, $threatEntryType, $platformType);
        $this->createTableIfNecessary($table);

        if ($removals) {
            $delete = delete()->from($table)->where(field('hashIndex')->in($removals));
            $this->connection->execute((string) $delete, $delete->getValues());
        }

        $items = [];
        $hashIndex = $this->getLastHashIndex($threatType, $threatEntryType, $platformType);
        foreach ($additions as $hash) {
            $rawHash = $hash->toSha256();
            $items[] = [
                'hashIndex' => $hashIndex++,
                'hashSha256' => $rawHash,
                'prefixSize' => mb_strlen($rawHash) / 2,
            ];
        }
        $insert = insert(...$items)->into($table, ...[
            'hashIndex',
            'hashSha256',
            'prefixSize',
        ]);
        foreach ($insert->split($this->buffer) as $insert) {
            $this->connection->execute((string) $insert, $insert->getValues());
        }

        $this->reorderHashes($threatType, $threatEntryType, $platformType);
        unset($this->prefixSizes[$threatType][$threatEntryType][$platformType]);
    }


    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @throws \InvalidArgumentException
     */
    private function reorderHashes(string $threatType, string $threatEntryType, string $platformType): void
    {
        $this->connection->execute("SELECT @hashIndex := -1");
        $update = update($this->getTableName($threatType, $threatEntryType, $platformType))
            ->set('hashIndex = (select @hashIndex := @hashIndex + 1)')
            ->orderBy('hashSha256');
        $this->connection->execute((string) $update, $update->getValues());
    }

    private function getLastHashIndex(string $threatType, string $threatEntryType, string $platformType): int
    {
        $query = select('MAX(hashIndex)')->from($this->getTableName($threatType, $threatEntryType, $platformType));
        $result = $this->connection->execute((string) $query, $query->getValues());
        if (0 !== count($result)) {
            return (int) $result->asValue();
        }
        return -1;
    }

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return string
     */
    private function getTableName(string $threatType, string $threatEntryType, string $platformType): string
    {
        $tableName = strtolower(implode('_', [$threatType, $threatEntryType, $platformType]));
        if (null == $this->prefix) {
            return $tableName;
        }
        return sprintf('%s_%s', $this->prefix, $tableName);
    }

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return bool
     */
    private function tableExists(string $threatType, string $threatEntryType, string $platformType): bool
    {
        return (bool) count(
            $this->connection->execute(
                sprintf(
                    "SHOW TABLES LIKE '%s';",
                    $this->getTableName($threatType, $threatEntryType, $platformType)
                )
            )
        );
    }


    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        $this->connection->commit();
    }

    /**
     * @inheritDoc
     */
    public function containsHash(string $threatType, string $threatEntryType, string $platformType, Hash $hash): bool
    {
        $select = select('1')->from($this->getTableName($threatType, $threatEntryType, $platformType))
            ->andWhere('hashSha256 = ?', $hash->toSha256())
            ->limit(1);
        $stmt = $this->connection->prepare((string) $select, $select->getValues());
        return (bool) count($this->connection->execute($stmt));
    }

    /**
     * @param string $tableName
     */
    private function createTableIfNecessary(string $tableName): void
    {
        $this->connection->execute(
            sprintf(
                "CREATE TABLE IF NOT EXISTS `%s` (
                 `hashIndex` MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT,
                 `hashSha256` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL,
                 `prefixSize` SMALLINT(5) UNSIGNED NOT NULL,
                 `updatedAt` DATETIME NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                 PRIMARY KEY `hashSha256` (`hashSha256`),
                 KEY (`hashIndex`),
                 KEY `prefixSize` (`prefixSize`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci",
                $tableName
            )
        );
    }
}
