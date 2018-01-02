<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Specification;

use function BenTools\Bl4cklistCh3ck3r\s;
use BenTools\HostnameExtractor\HostnameExtractor;
use BenTools\Specification\Exception\UnmetSpecificationException;
use function BenTools\Specification\spec;
use BenTools\Specification\Specification;
use Psr\Http\Message\UriInterface;
use function BenTools\Specification\reject;

class DomainMatchSpecification extends Specification
{
    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @var string
     */
    private $domain;

    /**
     * DomainMatchSpecification constructor.
     * @param HostnameExtractor $hostnameExtractor
     * @param UriInterface      $uri
     * @param string            $domainToMatch
     */
    public function __construct(UriInterface $uri, string $domainToMatch)
    {
        $this->uri = $uri;
        $this->domain = $domainToMatch;
    }

    /**
     * @inheritDoc
     */
    public function validate(): void
    {
        $hostname = strtolower($this->uri->getHost());
        $domain = strtolower($this->domain);

        $pattern = sprintf('`%s`u', $domain);
        $check = (bool) preg_match($pattern, $hostname);

        if (false === $check) {
            reject($this);
        }
    }
}