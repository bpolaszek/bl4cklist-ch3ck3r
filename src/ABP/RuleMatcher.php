<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP;

use BenTools\Bl4cklistCh3ck3r\ABP\Spec\ContextMatcherSpec;
use BenTools\Bl4cklistCh3ck3r\ABP\Spec\RuleSpecBuilder;
use BenTools\Specification\Exception\UnmetSpecificationException;
use Psr\Http\Message\UriInterface;

final class RuleMatcher
{
    /**
     * @var RuleSpecBuilder
     */
    private $ruleSpecBuilder;

    /**
     * RuleMatcher constructor.
     */
    public function __construct(RuleSpecBuilder $ruleSpecBuilder)
    {
        $this->ruleSpecBuilder = $ruleSpecBuilder;
    }

    /**
     * @param UriInterface $uri
     * @param array        $context
     * @param iterable     $rules
     * @return bool
     */
    public function matchRuleset(UriInterface $uri, array $context, iterable $rules, array &$matchingRule = null): bool
    {
        if ($this->matchRule($uri, $context, $rules, $matchingRule)) {
            return true === $this->matchContext($context, $matchingRule) && false === $this->matchException($uri, $context, $rules, $matchingRule);
        }

        return false;
    }

    /**
     * @param UriInterface $uri
     * @param array        $context
     * @param iterable     $rules
     * @return bool
     */
    private function matchRule(UriInterface $uri, array $context, iterable $rules, array &$matchingRule = null): bool
    {
        foreach ($rules as $rule) {
            if (true === $rule['exception']) {
                continue;
            }

            try {
                $spec = $this->ruleSpecBuilder->getSpecification($uri, $context, $rule);
                $spec->validate();
                $matchingRule = $rule;
                return true;
            } catch (UnmetSpecificationException $e) {
            }
        }

        return false;
    }

    /**
     * @param UriInterface $uri
     * @param array        $context
     * @param iterable     $rules
     * @return bool
     */
    private function matchException(UriInterface $uri, array $context, iterable $rules, array &$matchingRule = null): bool
    {
        foreach ($rules as $rule) {
            if (false === $rule['exception']) {
                continue;
            }

            try {
                $spec = $this->ruleSpecBuilder->getSpecification($uri, $context, $rule);
                $spec->validate();
                $matchingRule = null;
                return true;
            } catch (UnmetSpecificationException $e) {
            }
        }

        return false;
    }

    /**
     * @param array $context
     * @param array $rule
     * @return bool
     */
    private function matchContext(array $context, array $rule): bool
    {
        return (new ContextMatcherSpec($context, $rule))->isSatisfied();
    }

}
