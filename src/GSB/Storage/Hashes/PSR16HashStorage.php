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
        $storage = $this->cache->get($key) ?? [];
        $sizes = array_keys($storage);
        sort($sizes, SORT_NUMERIC);
        return $sizes;
    }

    /**
     * @inheritDoc
     */
    public function getHashes(string $threatType, string $threatEntryType, string $platformType): iterable
    {
        $key = $this->getKey($threatType, $threatEntryType, $platformType);
        $storage = $this->cache->get($key) ?? [];
        foreach (flatten($storage) as $sha256) {
            yield Hash::fromSha256($sha256);
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
    public function storeHashes(string $threatType, string $threatEntryType, string $platformType, array $hashes): void
    {
        $key = $this->getKey($threatType, $threatEntryType, $platformType);
        $storage = [];
        foreach ($hashes as $hash) {
            $length = mb_strlen((string) $hash) / 2;
            $storage[$length][] = (string) $hash;
        }
        $this->cache->set($key, $storage);
    }

    /**
     * @inheritDoc
     */
    public function containsHash(string $threatType, string $threatEntryType, string $platformType, Hash $hash): bool
    {
        foreach ($this->getHashes($threatType, $threatEntryType, $platformType) as $_hash) {
            if ($hash->toSha256() === $_hash->toSha256()) {
                return true;
            }
        }
        return false;
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
