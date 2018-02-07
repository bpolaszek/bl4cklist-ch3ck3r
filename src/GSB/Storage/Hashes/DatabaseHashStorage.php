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
    private $table;

    /**
     * @var int
     */
    private $buffer;

    /**
     * DatabaseHashStorage constructor.
     * @param AdapterInterface|MysqliAdapter|PdoAdapter $connection
     * @param string           $table
     * @param int              $buffer
     */
    public function __construct(AdapterInterface $connection, string $table, int $buffer = 20000)
    {
        $this->connection = $connection;
        $this->table = $table;
        $this->buffer = $buffer;
    }

    /**
     * @inheritDoc
     */
    public function getPrefixSizes(string $threatType, string $threatEntryType, string $platformType): array
    {
        $select = select('prefixSize')->distinct()->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType)
            ->orderBy('prefixSize DESC');
        return $this->connection->execute((string) $select, $select->getValues())->asList();
    }

    /**
     * @inheritDoc
     */
    public function getHashes(string $threatType, string $threatEntryType, string $platformType): iterable
    {
        $select = select('hashSha256')->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType)
            ->orderBy('hashIndex ASC');
        $result = $this->connection->execute((string) $select, $select->getValues());
        foreach ($result as $item) {
            yield Hash::fromSha256($item['hashSha256']);
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
    public function clearHashes(string $threatType, string $threatEntryType, string $platformType): void
    {
        $delete = delete()->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType);
        $this->connection->execute((string) $delete, $delete->getValues());
    }

    /**
     * @inheritDoc
     */
    public function storeHashes(string $threatType, string $threatEntryType, string $platformType, array $additions, array $removals): void
    {
        $wasEmpty = $this->isDatabaseEmpty($threatType, $threatEntryType, $platformType);
        $chunks = array_chunk($removals, 1000);
        foreach ($chunks as $chunk) {
            $delete = delete()->from($this->table)
                ->where('threatType = ?', $threatType)
                ->andWhere('threatEntryType = ?', $threatEntryType)
                ->andWhere('platformType = ?', $platformType)
                ->andWhere(field('hashIndex')->in($chunk))
            ;
            $this->connection->execute((string) $delete, $delete->getValues());
        }

        $chunks = array_chunk($additions, $this->buffer, true);

        foreach ($chunks as $chunk) {
            $items = [];
            foreach ($chunk as $h => $hash) {
                $items[] = [
                    'threatType'      => $threatType,
                    'threatEntryType' => $threatEntryType,
                    'platformType'    => $platformType,
                    'hashIndex'       => $h,
                    'hashSha256'      => $hash->toSha256(),
                    'prefixSize'      => mb_strlen($hash->toSha256()) / 2,
                ];
            }
            $insert = insert(...$items)->into($this->table, ...[
                'threatType',
                'threatEntryType',
                'platformType',
                'hashIndex',
                'hashSha256',
                'prefixSize',
            ]);
            $this->connection->execute((string) $insert, $insert->getValues());
        }

        if (!$wasEmpty) {
            $this->reorderHashes($threatType, $threatEntryType, $platformType);
        }
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
        $update = update($this->table)
            ->set('hashIndex = (select @hashIndex := @hashIndex + 1)')
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType)
            ->orderBy('hashSha256');
        $this->connection->execute((string) $update, $update->getValues());
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
        $select = select('1')->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType)
            ->andWhere('hashSha256 = ?', $hash->toSha256())
            ->limit(1)
        ;
        //dump($this->connection->prepare((string) $select, $select->getValues())->preview());
        return 1 === count($this->connection->execute((string) $select, $select->getValues()));
    }

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return bool
     * @throws \InvalidArgumentException
     */
    private function isDatabaseEmpty(string $threatType, string $threatEntryType, string $platformType): bool
    {
        $select = select('1')->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType)
            ->limit(1)
            ;
        return 0 === count($this->connection->execute((string) $select, $select->getValues()));
    }

    /**
     * @return string
     */
    public function getCreateTableQuery(): string
    {
        return <<<SQL
CREATE TABLE `{$this->table}` (
  `threatType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `threatEntryType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platformType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashIndex` int(10) UNSIGNED NOT NULL,
  `hashBase64` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `hashSha256` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefixSize` smallint(5) UNSIGNED NOT NULL,
  `updatedAt` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
  KEY `threatType` (`threatType`,`threatEntryType`,`platformType`),
  KEY `hashIndex` (`hashIndex`)
);
SQL;
    }
}
