<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Spec;

use BenTools\Specification\Specification;
use Psr\Http\Message\UriInterface;

final class RequestUriMatcherSpec extends Specification
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
        $requestUri = $this->uri->getPath();
        if ('' !== $this->uri->getQuery()) {
            $requestUri .= '?' . $this->uri->getQuery();
        }

        if ('' === $this->target) {
            self::factory('' === $requestUri)->validate();
            return;
        }

        self::factory(false !== strpos($requestUri, $this->target))->validate();
    }
}
