<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes;

use BenTools\Bl4cklistCh3ck3r\GSB\Model\Hash;

interface HashStorageInterface
{

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return array
     */
    public function getPrefixSizes(string $threatType, string $threatEntryType, string $platformType): array;

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return iterable|Hash[]
     */
    public function getHashes(string $threatType, string $threatEntryType, string $platformType): iterable;

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return Hash
     */
    public function getCheckSum(string $threatType, string $threatEntryType, string $platformType): Hash;

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @param Hash[] $hashes
     */
    public function storeHashes(string $threatType, string $threatEntryType, string $platformType, array $hashes): void;

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @param Hash   $hash
     * @param int    $prefixSize
     * @return bool
     */
    public function containsHash(string $threatType, string $threatEntryType, string $platformType, Hash $hash): bool;
}
