<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Model;

class DomainOption extends Option
{
    /**
     * @var string
     */
    private $domainName;

    /**
     * DomainOption constructor.
     * @param string $domainName
     * @param bool   $whiteListed
     */
    public function __construct(string $domainName, bool $whiteListed)
    {
        parent::__construct('domain', $whiteListed);
        $this->domainName = $domainName;
    }

    /**
     * @return string
     */
    public function getDomainName(): string
    {
        return $this->domainName;
    }


}