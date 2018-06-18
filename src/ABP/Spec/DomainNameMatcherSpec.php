<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Spec;

use BenTools\HostnameExtractor\ParsedHostname;
use BenTools\Specification\Specification;
use function BenTools\Specification\spec;
use function Stringy\create as s;

final class DomainNameMatcherSpec extends Specification
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
     * @param ParsedHostname $hostname - The hostname to test
     * @param ParsedHostname $target - The rule hostname to match
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
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    private function matchSubDomains(): Specification
    {

        // foo.bar.example.com can match bar.example.com
        if (true === $this->matchSubdomains) {
            return spec(s($this->hostname->getSubdomain())->endsWith($this->target->getSubdomain()));
        }

        return spec($this->hostname->getSubdomain() === $this->target->getSubdomain());
    }

    /**
     * @return Specification
     * @throws \RuntimeException
     */
    private function matchDomain(): Specification
    {
        return spec($this->hostname->getDomain() === $this->target->getDomain());
    }

    /**
     * @return Specification
     * @throws \RuntimeException
     */
    private function matchSuffix(): Specification
    {
        return spec($this->hostname->getSuffix() === $this->target->getSuffix());
    }
}
