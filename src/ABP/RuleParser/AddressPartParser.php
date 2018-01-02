<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\RuleParser;
use Psr\Http\Message\UriInterface;
use function Stringy\create as s;

class AddressPartParser implements RuleParserInterface
{
    /**
     * @inheritDoc
     */
    public function getSpecification(string $rule, UriInterface $uri): bool
    {
        // TODO: Implement matches() method.
    }

    /**
     * @inheritDoc
     */
    public function supports(string $rule): bool
    {
        return false;
    }
}