<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes;

use BenTools\Bl4cklistCh3ck3r\GSB\Model\Hash;

class PhpFileHashStorage implements HashStorageInterface
{
    /**
     * @var string
     */
    private $storageDirectory;

    private $localStorage = [];

    private $tmpfile = [];

    /**
     * FileHashStorage constructor.
     * @param string $storageDirectory
     */
    public function __construct(string $storageDirectory)
    {
        $this->storageDirectory = $storageDirectory;
    }

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return string
     */
    private function getFilename(string $threatType, string $threatEntryType, string $platformType)
    {
        return sprintf('%s/%s_%s_%s.php', $this->storageDirectory, ...func_get_args());
    }

    /**
     * @inheritDoc
     */
    public function getPrefixSizes(string $threatType, string $threatEntryType, string $platformType): array
    {
        $rawHashes = $this->getRawhashes($threatType, $threatEntryType, $platformType);
        $sizes = array_unique(array_map(function (string $sha256) {
            return strlen($sha256) / 2;
        }, $rawHashes));
        sort($sizes, SORT_NUMERIC);
        return $sizes;
    }

    /**
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @return array
     */
    private function getRawHashes(string $threatType, string $threatEntryType, string $platformType): array
    {
        if (!is_readable($this->getFilename($threatType, $threatEntryType, $platformType))) {
            return [];
        }
        return include $this->getFilename($threatType, $threatEntryType, $platformType);
    }

    /**
     * @inheritDoc
     */
    public function getHashes(string $threatType, string $threatEntryType, string $platformType): iterable
    {
        $rawHashes = $this->getRawHashes(...func_get_args());
        foreach ($rawHashes as $rawHash) {
            if ('' === $rawHash) {
                continue;
            }
            yield Hash::fromSha256($rawHash);
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
        $this->tmpfile = [
            'source' => tempnam($this->storageDirectory, 'gsb'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function clearHashes(string $threatType, string $threatEntryType, string $platformType): void
    {
        $filename = $this->getFilename($threatType, $threatEntryType, $platformType);
        if (file_exists($filename)) {
            unlink($filename);
        }
    }

    /**
     * @inheritDoc
     */
    public function storeHashes(string $threatType, string $threatEntryType, string $platformType, array $additions, array $removals): void
    {
        $this->tmpfile['dest'] = $this->getFilename($threatType, $threatEntryType, $platformType);

        $rawHashes = $this->getRawhashes($threatType, $threatEntryType, $platformType);
        foreach ($removals as $index) {
            unset($rawHashes[$index]);
        }

        foreach ($additions as $hash) {
            $rawHashes[] = $hash->toSha256();
        }

        Hash::sort($rawHashes);

        file_put_contents($this->tmpfile['source'], '<?php ' .PHP_EOL . 'return ' . var_export($rawHashes, true) . ';');
    }

    /**
     * @inheritDoc
     */
    public function commit(): void
    {
        rename($this->tmpfile['source'], $this->tmpfile['dest']);
        $this->tmpfile = [];
    }

    /**
     * @inheritDoc
     */
    public function containsHash(string $threatType, string $threatEntryType, string $platformType, Hash $hash): bool
    {
        if (isset($this->localStorage[$threatType][$threatEntryType][$platformType])) {
            return in_array($hash->toSha256(), $this->localStorage[$threatType][$threatEntryType][$platformType]);
        }
        $this->localStorage[$threatType][$threatEntryType][$platformType] = $this->getRawhashes($threatType, $threatEntryType, $platformType);
        return $this->containsHash($threatType, $threatEntryType, $platformType, $hash);
    }
}
