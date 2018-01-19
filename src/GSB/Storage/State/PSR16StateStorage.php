<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage\State;

use Psr\SimpleCache\CacheInterface;

class PSR16StateStorage implements StateStorageInterface
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * PSR16StateStorage constructor.
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @inheritDoc
     */
    public function getState(string $threatType, string $threatEntryType, string $platformType): string
    {
        $states = $this->getAllStates();
        $key = $this->getKey($threatType, $threatEntryType, $platformType);
        return $states[$key] ?? '';
    }

    /**
     * @inheritDoc
     */
    public function setState(string $threatType, string $threatEntryType, string $platformType, string $state): void
    {
        $states = $this->getAllStates();
        $key = $this->getKey($threatType, $threatEntryType, $platformType);
        $states[$key] = $state;
        $this->cache->set('google_safebrowsing.state', $states);
    }

    /**
     * @inheritDoc
     */
    public function getAllStates(): array
    {
        $states = $this->cache->get('google_safebrowsing.state');
        return $states ?? [];
    }

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return string
     */
    private function getKey(string $threatType, string $threatEntryType, string $platformType): string
    {
        return sprintf('%s_%s_%s', $threatType, $threatEntryType, $platformType);
    }
}
