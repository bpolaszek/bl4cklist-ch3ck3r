<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Spec;

use BenTools\Specification\Specification;
use Psr\Http\Message\UriInterface;

final class UriMatcherSpec extends Specification
{
    /**
     * @var UriInterface
     */
    private $uri;
    
    /**
     * @var string
     */
    private $target;

    /**
     * RequestUriMatcherSpec constructor.
     * @param UriInterface $uri
     * @param string       $target
     */
    public function __construct(UriInterface $uri, string $target)
    {
        $this->uri = $uri;
        $this->target = $target;
    }

    /**
     * @inheritDoc
     */
    public function validate(): void
    {
        $uri = (string) $this->uri;

        if ('' === $this->target) {
        }

        self::factory(false !== strpos($uri, $this->target))->validate();
    }
}
