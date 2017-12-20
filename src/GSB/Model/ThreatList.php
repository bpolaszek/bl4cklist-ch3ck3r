<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Model;

class ThreatList implements \JsonSerializable
{
    /**
     * @var string
     */
    private $threatType;

    /**
     * @var string
     */
    private $threatEntryType;

    /**
     * @var string
     */
    private $platformType;

    /**
     * @var string
     */
    private $state;

    /**
     * @var array
     */
    private $constraints = [];

    /**
     * ThreatList constructor.
     * @param string $threatType
     * @param string $threatEntryType
     * @param string $platformType
     * @param string $state
     */
    public function __construct(string $threatType, string $threatEntryType, string $platformType, string $state = '')
    {
        $this->threatType = $threatType;
        $this->threatEntryType = $threatEntryType;
        $this->platformType = $platformType;
        $this->state = $state;
    }

    /**
     * @return string
     */
    public function getThreatType(): string
    {
        return $this->threatType;
    }

    /**
     * @return string
     */
    public function getThreatEntryType(): string
    {
        return $this->threatEntryType;
    }

    /**
     * @return string
     */
    public function getPlatformType(): string
    {
        return $this->platformType;
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->state ?? '';
    }

    /**
     * @param string $state
     * @return ThreatList
     */
    public function withState(string $state): self
    {
        $clone = clone $this;
        $clone->state = $state;
        return $clone;
    }

    /**
     * @param string $state
     * @return ThreatList
     */
    public function withConstraints(array $constraints): self
    {
        $clone = clone $this;
        $clone->constraints = $constraints;
        return $clone;
    }

    /**
     * @param array $list
     * @return iterable|self[]
     */
    public static function fromJsonList(array $list): iterable
    {
        foreach ($list as $item) {
            yield new self($item['threatType'], $item['threatEntryType'], $item['platformType'], $item['newClientState'] ?? '');
        }
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize()
    {
        return [
            'threatType'      => $this->threatType,
            'threatEntryType' => $this->threatEntryType,
            'platformType'    => $this->platformType,
            'state'           => $this->state,
        ];
    }
}
