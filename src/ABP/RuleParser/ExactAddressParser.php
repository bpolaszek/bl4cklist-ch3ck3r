<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\RuleParser;
use function BenTools\Specification\spec;
use BenTools\Specification\Specification;
use Psr\Http\Message\UriInterface;
use function BenTools\Bl4cklistCh3ck3r\s;

class ExactAddressParser implements RuleParserInterface
{
    /**
     * @inheritDoc
     */
    public function getSpecification(string $rule, UriInterface $uri): Specification
    {
        $rule = s($rule);
        $rule = $rule->substringBeforeFirst('$') ?: $rule;
        $pattern = $rule->removeLeft('|')->removeRight('|');
        if (!$rule->contains('*')) {
            return spec((string) $pattern === (string) $uri);
        } else {
            return spec(fnmatch((string) $pattern, (string) $uri));
        }
    }

    /**
     * @inheritDoc
     */
    public function supports(string $rule): bool
    {
        $rule = s($rule);
        $rule = $rule->substringBeforeFirst('$') ?: $rule;
        return $rule->startsWith('|')
            && !$rule->startsWith('||')
            && $rule->endsWith('|');
    }
}