<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage;

interface ThrottleStorageInterface
{

    /**
     * Return the wait duration in seconds before we can issue a new request.
     * If there's no current wait duration the implementation MUST return 0.
     *
     * @return int
     */
    public function getRemainingDuration(): int;

    /**
     * Set the wait duration in seconds before we can issue a new request.
     *
     * @param int $duration
     */
    public function setRemainingDuration(int $duration): void;
}
