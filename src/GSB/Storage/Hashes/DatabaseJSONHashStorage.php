<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes;

use BenTools\Bl4cklistCh3ck3r\GSB\Model\Hash;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\State\StateStorageInterface;
use BenTools\SimpleDBAL\Contract\AdapterInterface;
use function BenTools\Where\delete;
use function BenTools\Where\insert;
use function BenTools\Where\select;
use function BenTools\Where\update;

class DatabaseJSONHashStorage implements HashStorageInterface, StateStorageInterface
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
     * DatabaseJSONHashStorage constructor.
     * @param AdapterInterface $connection
     * @param string           $table
     */
    public function __construct(AdapterInterface $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * @inheritDoc
     */
    public function getPrefixSizes(string $threatType, string $threatEntryType, string $platformType): array
    {
        $rawHashes = $this->getRawhashes($threatType, $threatEntryType, $platformType);
        $sizes = array_unique(array_map(function (string $sha256) {
            return strlen($sha256) / 2;
        }, $rawHashes));
        sort($sizes, SORT_NUMERIC);
        return $sizes;
    }

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return array
     * @throws \InvalidArgumentException
     */
    private function getRawhashes(string $threatType, string $threatEntryType, string $platformType): array
    {
        $select = select('hashesSha256')->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType)
            ->limit(1);
        return json_decode($this->connection->execute((string) $select, $select->getValues())->asValue() ?? '[]', true);
    }

    /**
     * @inheritDoc
     */
    public function getHashes(string $threatType, string $threatEntryType, string $platformType): iterable
    {
        $rawHashes = $this->getRawhashes($threatType, $threatEntryType, $platformType);
        foreach ($rawHashes as $item) {
            yield Hash::fromSha256($item);
        }
    }

    /**
     * @inheritDoc
     */
    public function getCheckSum(string $threatType, string $threatEntryType, string $platformType): Hash
    {
        $select = select('checksum')->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType)
            ->limit(1);
        $checksum = $this->connection->execute((string) $select, $select->getValues())->asValue();
        return Hash::fromSha256($checksum);
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): void
    {
        $this->connection->getWrappedConnection()->beginTransaction();
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
        $rawHashes = $this->getRawhashes($threatType, $threatEntryType, $platformType);
        foreach ($removals as $index) {
            unset($rawHashes[$index]);
        }

        foreach ($additions as $hash) {
            $rawHashes[] = $hash->toSha256();
        }

        Hash::sort($rawHashes);

        $insert = insert([
            'threatType'      => $threatType,
            'threatEntryType' => $threatEntryType,
            'platformType'    => $platformType,
            'hashesSha256'    => json_encode($rawHashes),
            'checksum'        => Hash::fromMultipleHashes(...array_map(function (string $rawhash) {
                return Hash::fromSha256($rawhash);
            }, $rawHashes))->getChecksum()->toSha256(),
        ])
            ->into($this->table, ...[
                'threatType',
                'threatEntryType',
                'platformType',
                'hashesSha256',
                'checksum',
            ]);
        $this->clearHashes($threatType, $threatEntryType, $platformType);
        $this->connection->execute((string) $insert, $insert->getValues());
    }

    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        $this->connection->getWrappedConnection()->commit();
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
            ->andWhere('JSON_SEARCH(hashesSha256, "one", ?) IS NOT NULL', $hash->toSha256())
            ->limit(1);
        return 1 === count($this->connection->execute((string) $select, $select->getValues()));
    }

    /**
     * @inheritDoc
     */
    public function getState(string $threatType, string $threatEntryType, string $platformType): string
    {
        $select = select('state')->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType)
            ->limit(1);
        return $this->connection->execute((string) $select, $select->getValues())->asValue() ?? '';
    }

    /**
     * @inheritDoc
     */
    public function setState(string $threatType, string $threatEntryType, string $platformType, string $state): void
    {
        $update = update($this->table)
            ->set('state = ?', $state)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType);
        $this->connection->execute((string) $update, $update->getValues());
    }

    /**
     * @inheritDoc
     */
    public function getAllStates(): array
    {
        $select = select('state')->from($this->table);
        return $this->connection->execute((string) $select, $select->getValues())->asList();
    }
}
