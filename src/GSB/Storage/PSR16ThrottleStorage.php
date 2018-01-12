<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage;

use Psr\SimpleCache\CacheInterface;

class PSR16ThrottleStorage implements ThrottleStorageInterface
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * @inheritDoc
     */
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @inheritDoc
     */
    public function getRemainingDuration(): int
    {
        $time = $this->cache->get($this->getKey()) ?? 0;
        return max(0, $time - time());
    }

    /**
     * @inheritDoc
     */
    public function setRemainingDuration(int $duration): void
    {
        $this->cache->set($this->getKey(), time() + $duration, $duration);
    }

    /**
     * @inheritDoc
     */
    public function clearRemainingDuration(): void
    {
        $this->cache->delete($this->getKey());
    }

    /**
     * @return string
     */
    private function getKey(): string
    {
        return 'google_safebrowsing.throttle_time';
    }
}
