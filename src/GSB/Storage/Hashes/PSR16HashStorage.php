<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes;

use BenTools\Bl4cklistCh3ck3r\GSB\Model\Hash;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes\HashStorageInterface;
use Psr\SimpleCache\CacheInterface;
use function BenTools\FlattenIterator\flatten;

class PSR16HashStorage implements HashStorageInterface
{
    /**
     * @var CacheInterface
     */
    private $cache;

    /**
     * PSR16Storage constructor.
     * @param CacheInterface $cache
     */
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @inheritDoc
     */
    public function getPrefixSizes(string $threatType, string $threatEntryType, string $platformType): array
    {
        $key = $this->getKey($threatType, $threatEntryType, $platformType);
        $rawHashes = $this->cache->get($key) ?? [];
        $sizes = array_unique(array_map(function (string $sha256) {
            return strlen($sha256) / 2;
        }, $rawHashes));
        sort($sizes, SORT_NUMERIC);
        return $sizes;
    }

    /**
     * @inheritDoc
     */
    public function getHashes(string $threatType, string $threatEntryType, string $platformType): iterable
    {
        $key = $this->getKey($threatType, $threatEntryType, $platformType);
        $rawHashes = $this->cache->get($key) ?? [];
        foreach ($rawHashes as $string) {
            yield Hash::fromSha256($string);
        }
    }

    /**
     * @inheritDoc
     */
    public function getCheckSum(string $threatType, string $threatEntryType, string $platformType): Hash
    {
        $hashes = iterable_to_array($this->getHashes($threatType, $threatEntryType, $platformType));
        $fullchain = Hash::fromMultipleHashes(...$hashes);
        return $fullchain->getChecksum();
    }

    /**
     * @inheritDoc
     */
    public function beginTransaction(): void
    {
        return;
    }

    /**
     * @inheritDoc
     */
    public function clearHashes(string $threatType, string $threatEntryType, string $platformType): void
    {
        $key = $this->getKey($threatType, $threatEntryType, $platformType);
        $this->cache->delete($key);
    }

    /**
     * @inheritDoc
     */
    public function storeHashes(string $threatType, string $threatEntryType, string $platformType, array $additions, array $removals): void
    {
        $key = $this->getKey($threatType, $threatEntryType, $platformType);
        $rawHashes = $this->cache->get($key) ?? [];

        // Process removals first
        foreach ($removals as $index) {
            unset($rawHashes[$index]);
        }

        // Then, process additions
        foreach ($additions as $hash) {
            $rawHashes[] = $hash->toSha256();
        }

        Hash::sort($rawHashes);
        $this->cache->set($key, $rawHashes);
    }


    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        return;
    }

    /**
     * @inheritDoc
     */
    public function containsHash(string $threatType, string $threatEntryType, string $platformType, Hash $hash): bool
    {
        $key = $this->getKey($threatType, $threatEntryType, $platformType);
        $storage = $this->cache->get($key) ?? [];
        return in_array($hash->toSha256(), $storage);
    }


    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return string
     */
    private function getKey(string $threatType, string $threatEntryType, string $platformType): string
    {
        return sprintf('google_safebrowsing.hashes.%s.%s.%s', $threatType, $threatEntryType, $platformType);
    }
}
