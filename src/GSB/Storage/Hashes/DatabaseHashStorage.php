<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes;

use BenTools\Bl4cklistCh3ck3r\GSB\Model\Hash;
use BenTools\SimpleDBAL\Contract\AdapterInterface;
use function BenTools\Where\delete;
use function BenTools\Where\insert;
use function BenTools\Where\select;

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
     * @param AdapterInterface $connection
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
        $select = select('hashBase64')->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType)
            ->orderBy('hashIndex ASC');
        $result = $this->connection->execute((string) $select, $select->getValues());
        foreach ($result as $item) {
            yield Hash::fromBase64($item['hashBase64']);
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
    public function storeHashes(string $threatType, string $threatEntryType, string $platformType, array $hashes): void
    {
        $delete = delete()->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType);

        $items = [];
        /** @var Hash[] $hashes */
        foreach ($hashes as $h => $hash) {
            $items[] = [
                'threatType'      => $threatType,
                'threatEntryType' => $threatEntryType,
                'platformType'    => $platformType,
                'hashIndex'       => $h,
                'hashBase64'      => $hash->toBase64(),
                'prefixSize'      => mb_strlen($hash->toSha256()) / 2,
            ];
        }

        $insert = insert(...$items)->into($this->table, ...[
            'threatType',
            'threatEntryType',
            'platformType',
            'hashIndex',
            'hashBase64',
            'prefixSize',
        ]);

        $this->connection->execute((string) $delete, $delete->getValues());

        foreach ($insert->split($this->buffer) as $insert) {
            $this->connection->execute((string) $insert, $insert->getValues());
        }

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
            ->andWhere('hashBase64 = ?', $hash->toBase64())
            ->limit(1)
        ;
        return 1 === count($this->connection->execute((string) $select, $select->getValues()));
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
  `hashIndex` int(10) unsigned NOT NULL,
  `hashBase64` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefixSize` smallint(5) unsigned NOT NULL,
  KEY `threatType` (`threatType`,`threatEntryType`,`platformType`),
  KEY `hashIndex` (`hashIndex`)
);
SQL;

    }
}