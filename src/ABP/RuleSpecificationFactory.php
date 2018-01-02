<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP;

use BenTools\Bl4cklistCh3ck3r\ABP\Model\DomainOption;
use BenTools\Bl4cklistCh3ck3r\ABP\Model\Option;
use BenTools\Bl4cklistCh3ck3r\ABP\Model\Rule;
use BenTools\Bl4cklistCh3ck3r\ABP\Specification\DomainMatchSpecification;
use BenTools\Specification\Specification;
use Psr\Http\Message\UriInterface;
use function BenTools\Bl4cklistCh3ck3r\s;
use function BenTools\Specification\not;
use function BenTools\Specification\spec;

class RuleSpecificationFactory
{
    /**
     * @param Rule         $rule
     * @param UriInterface $uri
     * @param array        $context
     * @return Specification
     * @throws \RuntimeException
     */
    public function createSpecification(Rule $rule, UriInterface $uri, array $context = []): Specification
    {
        return $this->createPatternSpecification($rule, $uri, $context)
            ->and($this->createOptionsSpecification($rule, $uri, $context));
    }

    /**
     * @param Rule         $rule
     * @param UriInterface $uri
     * @param array        $context
     * @return Specification
     * @throws \RuntimeException
     */
    public function createPatternSpecification(Rule $rule, UriInterface $uri, array $context = []): Specification
    {
        switch ($rule->getBlockingType()) {
            case Rule::BLOCK_EXACT_ADDRESS:

                if ($rule->isRegexp()) {

                    if ($rule->getPattern()->startsWith('http')) {
                        return spec(s((string) $uri)->matches($this->createRegexpPattern($rule->getPattern())));
                    }

                    return spec(s($this->withoutScheme($uri))->matches($this->createRegexpPattern($rule->getPattern())));

                } else {
                    if ($rule->getPattern()->startsWith('http')) {
                        return spec((string) $rule->getPattern() === (string) $uri);
                    }
                    return spec((string) $rule->getPattern() === $this->withoutScheme($uri));
                }

                break;

            case Rule::BLOCK_BY_DOMAIN_NAME:
                $domainPattern = $rule->getPattern()->substringBeforeFirst('/') ?: $rule->getPattern();

                if ($rule->isRegexp()) {
                    $spec = new DomainMatchSpecification($uri, $domainPattern);
                } else {
                    $spec = spec(s($uri->getHost())->endsWith($domainPattern));
                }
                if (false !== ($pathPattern = $rule->getPattern()->substringAfterFirst('/'))) {
                    return spec(s($this->onlyPathAndQuery($uri))->matches($this->createRegexpPattern($pathPattern)));
                }

                return $spec;

                break;
            case Rule::BLOCK_BY_ADDRESS_PART:
                return spec(s($this->withoutScheme($uri))->matches($this->createRegexpPattern($rule->getPattern())));

            default:
                return spec(false);

        }
    }

    /**
     * @param Rule         $rule
     * @param UriInterface $uri
     * @param array        $context
     * @return Specification
     * @throws \RuntimeException
     */
    public function createOptionsSpecification(Rule $rule, UriInterface $uri, array $context = []): Specification
    {
        $spec = spec(true);
        foreach ($rule->getOptions() as $option) {
            $spec = $spec->and($this->createOptionSpecification($rule, $option, $uri, $context));
        }
        return $spec;
    }

    /**
     * @param Rule         $rule
     * @param Option       $option
     * @param UriInterface $uri
     * @param array        $context
     * @return Specification
     * @throws \RuntimeException
     */
    private function createOptionSpecification(Rule $rule, Option $option, UriInterface $uri, array $context): Specification
    {
        if ($option instanceof DomainOption) {
            $spec = new DomainMatchSpecification($uri, $option->getDomainName());
            return $option->isWhiteListed() ? not($spec) : $spec;
        }
        $spec = spec(true);
        foreach ($context as $value) {
            $spec = $spec->and($rule->hasOption($value))
                ->and($rule->hasOption($value) && !$rule->getOption($value)->isWhiteListed());
        }
    }

    /**
     * @param UriInterface $uri
     * @return string
     */
    private function withoutScheme(UriInterface $uri): string
    {
        return (string) s((string) $uri)->removeLeft(sprintf('%s://', $uri->getScheme()));
    }

    /**
     * @param UriInterface $uri
     * @return string
     */
    private function onlyPathAndQuery(UriInterface $uri): string
    {
        $string = '';
        if ('' !== $uri->getPath()) {
            $string .= $uri->getPath();
        }
        if ('' !== $uri->getQuery()) {
            $string .= sprintf('?%s', $uri->getQuery());
        }
        return $string;
    }

    /**
     * @param string $pattern
     * @return string
     */
    private function createRegexpPattern(string $pattern): string
    {
        return sprintf('`%s`iu', $pattern);
    }
}