<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Model;

class BlacklistedState
{
    /**
     * @var string
     */
    private $expression;

    /**
     * @var Hash
     */
    private $hash;

    /**
     * @var int
     */
    private $length;

    /**
     * @var ThreatList
     */
    private $threatList;

    /**
     * @var bool
     */
    private $shouldBeChecked = false;

    /**
     * @var bool
     */
    private $blacklisted = false;

    /**
     * BlacklistedState constructor.
     * @param string     $expression
     * @param Hash       $fullhash
     * @param int        $length
     * @param ThreatList $threatList
     */
    public function __construct(string $expression, Hash $fullhash, int $length, ThreatList $threatList)
    {
        $this->expression = $expression;
        $this->hash = $fullhash;
        $this->length = $length;
        $this->threatList = $threatList;
    }

    /**
     * @return bool
     */
    public function shouldBeChecked(): bool
    {
        return $this->shouldBeChecked;
    }

    /**
     * @param bool $shouldBeChecked
     */
    public function setShouldBeChecked(bool $shouldBeChecked): void
    {
        $this->shouldBeChecked = $shouldBeChecked;
    }

    /**
     * @return bool
     */
    public function isBlacklisted(): bool
    {
        return $this->blacklisted;
    }

    /**
     * @param bool $blacklisted
     */
    public function setBlacklisted(bool $blacklisted): void
    {
        $this->blacklisted = $blacklisted;
    }

    /**
     * @return ThreatList
     */
    public function getThreatList(): ThreatList
    {
        return $this->threatList;
    }

    /**
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * @return Hash
     */
    public function getHash(): Hash
    {
        return $this->hash;
    }

    /**
     * @return int
     */
    public function getLength(): int
    {
        return $this->length;
    }
}
