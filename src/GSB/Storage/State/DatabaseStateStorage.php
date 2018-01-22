<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage\State;

use BenTools\SimpleDBAL\Contract\AdapterInterface;
use function BenTools\Where\delete;
use function BenTools\Where\insert;
use function BenTools\Where\select;

class DatabaseStateStorage implements StateStorageInterface
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
     * DatabaseStateStorage constructor.
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
        $delete = delete()->from($this->table)
            ->where('threatType = ?', $threatType)
            ->andWhere('threatEntryType = ?', $threatEntryType)
            ->andWhere('platformType = ?', $platformType);

        $insert = insert([
            'threatType'      => $threatType,
            'threatEntryType' => $threatEntryType,
            'platformType'    => $platformType,
            'state'           => $state,
        ])
            ->into($this->table);

        $this->connection->execute((string) $delete, $delete->getValues());
        $this->connection->execute((string) $insert, $insert->getValues());
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
