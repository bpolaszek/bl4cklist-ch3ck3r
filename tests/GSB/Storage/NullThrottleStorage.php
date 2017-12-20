<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\GSB\Storage;

use BenTools\Bl4cklistCh3ck3r\GSB\Storage\ThrottleStorageInterface;

class NullThrottleStorage implements ThrottleStorageInterface
{

    /**
     * @inheritDoc
     */
    public function getRemainingDuration(): int
    {
        return 0;
    }

    /**
     * @inheritDoc
     */
    public function setRemainingDuration(int $duration): void
    {
        return;
    }
}