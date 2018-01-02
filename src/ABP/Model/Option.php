<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Model;

class Option
{
    /**
     * @var string
     */
    private $type;

    /**
     * @var bool
     */
    protected $whiteListed;

    /**
     * Option constructor.
     * @param string $type
     * @param bool   $whiteListed
     */
    public function __construct(string $type, bool $whiteListed)
    {
        $this->type = $type;
        $this->whiteListed = $whiteListed;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isWhiteListed(): bool
    {
        return $this->whiteListed;
    }
}