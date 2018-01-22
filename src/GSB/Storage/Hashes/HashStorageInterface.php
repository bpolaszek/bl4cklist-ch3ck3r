<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes;

use BenTools\Bl4cklistCh3ck3r\GSB\Model\Hash;

interface HashStorageInterface
{

    /**
     * Return the different prefix sizes, ordered DESC.
     *
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return array
     */
    public function getPrefixSizes(string $threatType, string $threatEntryType, string $platformType): array;

    /**
     * Iterate over all hashes in the correct order.
     *
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return iterable|Hash[]
     */
    public function getHashes(string $threatType, string $threatEntryType, string $platformType): iterable;

    /**
     * Return the checksum of all the hashes.
     *
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return Hash
     */
    public function getCheckSum(string $threatType, string $threatEntryType, string $platformType): Hash;

    /**
     * Start a removal / addition transaction, if supported.
     */
    public function beginTransaction(): void;

    /**
     * Clear local database.
     *
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     */
    public function clearHashes(string $threatType, string $threatEntryType, string $platformType): void;

    /**
     * Store all hashes in the correct order.
     * The implementation MUST process removals before additions, then store hashes in natural order.
     *
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @param Hash[] $additions - an array of Hash objects
     * @param int[] $removals - a list of indices to remove
     */
    public function storeHashes(string $threatType, string $threatEntryType, string $platformType, array $additions, array $removals): void;

    /**
     * Commit the current transaction.
     */
    public function commit(): void;

    /**
     * Return wether or not this hash matches the local database.
     *
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @param Hash   $hash
     * @param int    $prefixSize
     * @return bool
     */
    public function containsHash(string $threatType, string $threatEntryType, string $platformType, Hash $hash): bool;
}
