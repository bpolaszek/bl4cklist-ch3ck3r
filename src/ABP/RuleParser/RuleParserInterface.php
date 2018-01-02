<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\RuleParser;

use BenTools\Specification\Specification;
use Psr\Http\Message\UriInterface;

interface RuleParserInterface
{
    /**
     * @param string $rule
     * @return bool
     */
    public function supports(string $rule): bool;

    /**
     * @param string       $rule
     * @param UriInterface $uri
     * @return bool
     */
    public function getSpecification(string $rule, UriInterface $uri): Specification;

}