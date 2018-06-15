<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Spec;

use BenTools\HostnameExtractor\ParsedHostname;
use BenTools\Specification\Exception\UnmetSpecificationException;
use BenTools\Specification\Specification;
use function BenTools\Specification\spec;
use function Stringy\create as s;

final class DomainNameMatchSpec extends Specification
{
    /**
     * @var ParsedHostname
     */
    private $hostname;

    /**
     * @var ParsedHostname
     */
    private $target;

    /**
     * @var bool
     */
    private $matchSubdomains;

    /**
     * DomainNameMatchSpec constructor.
     * @param ParsedHostname $hostname
     * @param ParsedHostname $target
     * @param bool           $matchSubdomains
     */
    public function __construct(ParsedHostname $hostname, ParsedHostname $target, bool $matchSubdomains = true)
    {
        $this->hostname = $hostname;
        $this->target = $target;
        $this->matchSubdomains = $matchSubdomains;
    }

    /**
     * @inheritDoc
     */
    public function validate(): void
    {
        $this->matchSubdomains()->and(
            $this->matchDomain()
        )
            ->and(
                $this->matchSuffix()
            )
            ->validate();
    }

    /**
     * @return Specification
     * @throws \RuntimeException
     */
    private function shouldMatchSubdomains(): Specification
    {
        return spec($this->matchSubdomains);
    }

    /**
     * @return Specification
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    private function matchSubDomains(): Specification
    {
        return $this->shouldMatchSubdomains()->and(spec(s($this->hostname->getSubdomain())->endsWith($this->target->getSubdomain()))
            ->or('*' === $this->target->getSubdomain()));
    }

    /**
     * @return Specification
     * @throws \RuntimeException
     */
    private function matchDomain(): Specification
    {
        return spec($this->hostname->getDomain() === $this->target->getDomain())
            ->or('*' === $this->target->getDomain());
    }

    /**
     * @return Specification
     * @throws \RuntimeException
     */
    private function matchSuffix(): Specification
    {
        return spec($this->hostname->getSuffix() === $this->target->getSuffix())
            ->or('*' === $this->target->getSuffix());
    }
}
