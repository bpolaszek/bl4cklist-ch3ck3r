<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\RuleParser;
use BenTools\Specification\Specification;
use Psr\Http\Message\UriInterface;
use function BenTools\Bl4cklistCh3ck3r\s;

class DomainNameParser implements RuleParserInterface
{
    /**
     * @inheritDoc
     */
    public function getSpecification(string $rule, UriInterface $uri): Specification
    {
        $host = $uri->getHost();
        $rule = s($rule)->removeLeft('||');
    }

    /**
     * @inheritDoc
     */
    public function supports(string $rule): bool
    {
        return s($rule)->startsWith('||');
    }
}