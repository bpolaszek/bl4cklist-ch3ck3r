<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Model;

use function BenTools\Bl4cklistCh3ck3r\s;
use BenTools\Bl4cklistCh3ck3r\Stringy\Stringy;

class Rule
{

    const BLOCK_BY_ADDRESS_PART = 1;
    const BLOCK_BY_DOMAIN_NAME = 2;
    const BLOCK_EXACT_ADDRESS = 3;
    const SEPARATOR_REGEXP = '([^\w\_\-\.\%]+)';
    const NOT_SEPARATOR_REGEXP = '([\w\_\-\.\%]+)';

    /**
     * @var int
     */
    private $blockingType;

    /**
     * @var Stringy
     */
    private $pattern;

    /**
     * @var Option[]|DomainOption[]
     */
    private $options;
    /**
     * @var bool
     */
    private $regexp;

    /**
     * Rule constructor.
     * @param int     $blockingType
     * @param Stringy $pattern
     * @param array   $options
     * @param bool    $regexp
     */
    protected function __construct(int $blockingType, Stringy $pattern, array $options, bool $regexp)
    {
        $this->blockingType = $blockingType;
        $this->pattern = $pattern;
        $this->options = $options;
        $this->regexp = $regexp;
    }

    /**
     * @return int
     */
    public function getBlockingType(): int
    {
        return $this->blockingType;
    }

    /**
     * @return Stringy
     */
    public function getPattern(): Stringy
    {
        return $this->pattern;
    }

    /**
     * @return Option[]|DomainOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * @param string $type
     * @return bool
     */
    public function hasOption(string $type): bool
    {
        foreach ($this->options as $option) {
            if ($type === $option->getType()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $type
     * @return Option|null
     */
    public function getOption(string $type): ?Option
    {
        foreach ($this->options as $option) {
            if ($type === $option->getType()) {
                return $option;
            }
        }
        return null;
    }

    /**
     * @return bool
     */
    public function isRegexp(): ?bool
    {
        return $this->regexp;
    }

    /**
     * @param string $string
     * @return Rule
     */
    public static function createFromString(string $string): self
    {
        $rule = s($string);
        $pattern = $rule->substringBeforeFirst('$') ?: $rule;
        $optionsParts = ($rule->substringAfterFirst('$') ?: s(''))->explode(',');
        if ($pattern->startsWith('||')) {
            $blockingType = self::BLOCK_BY_DOMAIN_NAME;
            $pattern = $pattern->removeLeft('||');
        } elseif ($pattern->startsWith('|') && $pattern->endsWith('|')) {
            $blockingType = self::BLOCK_EXACT_ADDRESS;
            $pattern = $pattern->removeLeft('|')->removeRight('|');
        } else {
            $blockingType = self::BLOCK_BY_ADDRESS_PART;
        }

        $isRegexp = $pattern->contains('^') || $pattern->contains('*') || $pattern->contains('\\');

        if ($pattern->contains('^') || $pattern->contains('*')) {
            $pattern = $pattern->replace('^', '[SEPARATOR_REGEXP]')
                ->replace('*', '[WILDCARD_REGEXP]');
            $pattern = s(strtr((string) $pattern, ['[SEPARATOR_REGEXP]' => self::SEPARATOR_REGEXP, '[WILDCARD_REGEXP]' => self::NOT_SEPARATOR_REGEXP]));
        }

        $options = [];

        /**
         * @var Stringy[] $optionsParts
         * @var Stringy[] $domains
         */
        foreach ($optionsParts as $optionsPart) {
            if ($optionsPart->startsWith('domain=')) {
                $domains = $optionsPart->removeLeft('domain=')->explode('|');
                foreach ($domains as $domain) {
                    $whiteListed = (bool) $domain->startsWith('~');
                    $options[] = new DomainOption((string) $domain->removeLeft('~'), $whiteListed);
                }
            } else {
                $whiteListed = (bool) $optionsPart->startsWith('~');
                $options[] = new Option((string) $optionsPart->removeLeft('~'), $whiteListed);
            }
        }


        return new self($blockingType, $pattern, $options, $isRegexp);
    }
}