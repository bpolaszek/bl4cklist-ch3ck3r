<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Spec;

use BenTools\Bl4cklistCh3ck3r\ABP\Parser\AdblockParser;
use BenTools\Specification\Exception\UnmetSpecificationException;
use function BenTools\Specification\spec;
use BenTools\Specification\Specification;

final class ContextMatcherSpec extends Specification
{
    /**
     * @var array
     */
    private $context;

    /**
     * @var array
     */
    private $rule;

    /**
     * ContextMatcherSpec constructor.
     * @param array $context
     * @param array $rule
     */
    public function __construct(array $context, array $rule)
    {
        $this->context = $context;
        $this->rule = $rule;
    }

    /**
     * @inheritDoc
     */
    public function validate(): void
    {
        $context = $this->context;
        $rule = $this->rule;

        foreach ($context as $filter => $value) {
            if (in_array($filter, AdblockParser::BOOLEAN_CONTEXTS)) {
                $_spec = spec(($rule['options'][$filter] ?? AdblockParser::IGNORE_FILTER) === $value);
                $spec = isset($spec) ? $spec->and($_spec) : $_spec;
            } elseif ('domain' === $filter && isset($rule['options']['domain'][$context['domain']])) {
                $status = $rule['options']['domain'][$context['domain']];
                $_spec = spec(AdblockParser::APPLY_FILTER === $status);
                $spec = isset($spec) ? $spec->and($_spec) : $_spec;
            }
        }

        if (!isset($spec)) {
            $spec = spec(true);
        }

        $spec->validate();
    }
}
