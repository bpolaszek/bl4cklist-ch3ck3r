<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Model;

use Serializable;

final class Hash implements Serializable
{
    /**
     * @var string
     */
    private $sha256;

    /**
     * @var string
     */
    private $base64;

    /**
     * @var self
     */
    private static $prototype;

    private function __construct()
    {
        self::$prototype = $this;
    }

    public function __clone()
    {
        $this->sha256 = null;
        $this->base64 = null;
    }

    /**
     * @return Hash
     */
    private static function new(): self
    {
        if (isset(self::$prototype)) {
            return clone self::$prototype;
        }
        return new self;
    }

    /**
     * @param string $sha256
     * @return Hash
     */
    private function setSha256(string $sha256): self
    {
        $this->sha256 = $sha256;
        return $this;
    }

    /**
     * @return string
     */
    public function toSha256(): string
    {
        return null !== $this->sha256 ? $this->sha256 : $this->setSha256(bin2hex(base64_decode($this->base64)))->sha256;
    }

    /**
     * @param string $base64
     * @return Hash
     */
    private function setBase64(string $base64): self
    {
        $this->base64 = $base64;
        return $this;
    }

    /**
     * @return string
     */
    public function toBase64(): string
    {
        return null !== $this->base64 ? $this->base64 : $this->setBase64(base64_encode(hex2bin($this->sha256)))->base64;
    }

    /**
     * @param int $length
     * @return string
     */
    public function shorten(int $length): self
    {
        return self::fromSha256(substr($this->toSha256(), 0, $length));
    }

    /**
     * @param Hash $hash
     * @param int  $prefixSize
     * @return bool
     */
    public function contains(Hash $hash, int $prefixSize): bool
    {
        $string = $this->toSha256();
        $hashes = str_split($string, $prefixSize * 2);
        return in_array($hash->toSha256(), $hashes, true);
    }

    /**
     * @param int $prefixSize
     * @return Hash[]
     */
    public function getSplitHashes(int $prefixSize): iterable
    {
        $sha256 = $this->toSha256();
        $length = strlen($sha256);
        $realSize = $prefixSize * 2;
        for ($i = 0; $i < $length; $i += $realSize) {
            yield Hash::fromSha256(substr($sha256, $i, $realSize));
        }

        /*return array_map(function (string $hash) {
            return Hash::fromSha256($hash);
        }, str_split($this->toSha256(), $prefixSize * 2));*/
    }

    /**
     * @return Hash
     */
    public function getChecksum(): Hash
    {
        return self::fromSha256(hash('sha256', hex2bin((string) $this->toSha256())));
    }

    /**
     * @param string $input
     * @return Hash
     */
    public static function fromSha256(string $input): self
    {
        $hash = self::new();
        $hash->sha256 = $input;
        return $hash;
    }

    /**
     * @param string $input
     * @return Hash
     */
    public static function fromBase64(string $input): self
    {
        $hash = self::new();
        $hash->base64 = $input;
        return $hash;
    }

    /**
     * @param string $input
     * @return Hash
     */
    public static function fromUnhashedString(string $input): self
    {
        return self::fromSha256(hash('sha256', $input));
    }

    /**
     * @param Hash[] ...$hashes
     * @return Hash
     */
    public static function fromMultipleHashes(Hash ...$hashes)
    {
        $sha256 = '';
        foreach ($hashes as $hash) {
            $sha256 .= $hash->toSha256();
        }
        return self::fromSha256($sha256);
    }

    /**
     * @param array $hashes
     * @return array
     */
    public static function sort(array &$hashes)
    {
        sort($hashes, SORT_STRING);
        return $hashes;
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->toSha256();
    }

    /**
     * @inheritDoc
     */
    public function serialize()
    {
        return $this->toBase64();
    }

    /**
     * @inheritDoc
     */
    public function unserialize($serialized)
    {
        $this->base64 = $serialized;
    }
}
