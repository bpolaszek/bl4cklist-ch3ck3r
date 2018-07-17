<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Spec;

use BenTools\Bl4cklistCh3ck3r\ABP\Parser\AdblockParser;
use BenTools\HostnameExtractor\HostnameExtractor;
use function BenTools\Specification\spec;
use BenTools\Specification\Specification;
use Psr\Http\Message\UriInterface;
use function BenTools\UriFactory\Helper\uri;

final class RuleSpecBuilder
{
    /**
     * @var HostnameExtractor
     */
    private $hostnameExtractor;

    /**
     * RuleSpecBuilder constructor.
     * @param HostnameExtractor $hostnameExtractor
     */
    public function __construct(HostnameExtractor $hostnameExtractor)
    {
        $this->hostnameExtractor = $hostnameExtractor;
    }

    /**
     * @param UriInterface $uri
     * @param array        $context
     * @param array        $rule
     * @return Specification
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function getSpecification(UriInterface $uri, array $context = [], array $rule): Specification
    {
        switch ($rule['type']) {
            case AdblockParser::EXACT_MATCH:
                $spec = spec((string) $uri === (string) $rule['pattern']);
                break;

            case AdblockParser::DOMAIN_NAME:
                $ruleHostname = $this->hostnameExtractor->extract($rule['hostname']);
                $uriHostName = $this->hostnameExtractor->extract($uri->getHost());
                $spec = new DomainNameMatcherSpec($uriHostName, $ruleHostname, true);
                break;

            case AdblockParser::DOMAIN_URI:
                $ruleHostname = $this->hostnameExtractor->extract($rule['hostname']);
                $uriHostName = $this->hostnameExtractor->extract($uri->getHost());
                $spec = (new DomainNameMatcherSpec($uriHostName, $ruleHostname, true))->and(
                    new RequestUriMatcherSpec($uri, $rule['request_uri'])
                );
                break;

            case AdblockParser::ADDRESS_PARTS:
                $spec = '' === $rule['pattern'] ? spec(false) : new UriMatcherSpec($uri, $rule['pattern']);
                break;

            default:
                throw new \InvalidArgumentException("Unable to create specification");
        }

        //$spec = $spec->and(new ContextMatcherSpec($context, $rule));

        return $spec;
    }
}
