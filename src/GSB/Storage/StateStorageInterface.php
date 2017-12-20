<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage;

interface StateStorageInterface
{

    /**
     * Return the current state of the given threat list.
     * If the current state is not known, the implementation MUST return an empty string.
     *
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return string
     */
    public function getState(string $threatType, string $threatEntryType, string $platformType): string;

    /**
     * Set the current state of the given threat list.
     *
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @param string $state
     */
    public function setState(string $threatType, string $threatEntryType, string $platformType, string $state): void;

    /**
     * @return array
     */
    public function getAllStates(): array;
}
