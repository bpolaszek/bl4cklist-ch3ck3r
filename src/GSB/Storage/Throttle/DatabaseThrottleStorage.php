<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage\Throttle;

use BenTools\SimpleDBAL\Contract\AdapterInterface;
use function BenTools\Where\delete;
use function BenTools\Where\insert;
use function BenTools\Where\select;

class DatabaseThrottleStorage implements ThrottleStorageInterface
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
     * DatabaseThrottleStorage constructor.
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
    public function getRemainingDuration(): int
    {
        $select = select('waitUntil')->from($this->table)->limit(1);
        $value = $this->connection->execute((string) $select)->asValue();
        if (null === $value) {
            return 0;
        }
        return max(0, (new \DateTime($value))->format('U') - time());
    }

    /**
     * @inheritDoc
     */
    public function setRemainingDuration(int $duration): void
    {
        $this->clearRemainingDuration();
        $insert = insert(['waitUntil' => new \DateTime(sprintf('+ %ds', $duration))]);
        $this->connection->execute((string) $insert);
    }

    /**
     * @inheritDoc
     */
    public function clearRemainingDuration(): void
    {
        $delete = delete()->from($this->table);
        $this->connection->execute((string) $delete);
    }
}