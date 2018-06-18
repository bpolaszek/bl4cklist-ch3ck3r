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

        foreach (AdblockParser::BOOLEAN_CONTEXTS as $filter) {
            if (isset($context[$filter])) {
                $_spec = spec(($rule['options'][$filter] ?? AdblockParser::IGNORE_FILTER) === AdblockParser::APPLY_FILTER);
                $spec = isset($spec) ? $spec->and($_spec) : $_spec;
            }
        }

        if (isset($context['domain']) && isset($rule['options']['domain'][$context['domain']])) {
            $status = $rule['options']['domain'][$context['domain']];
            $_spec = spec(AdblockParser::APPLY_FILTER === $status);
            $spec = isset($spec) ? $spec->and($_spec) : $_spec;
        }

        if (!isset($spec)) {
            $spec = spec(true);
        }

        $spec->validate();
    }
}
