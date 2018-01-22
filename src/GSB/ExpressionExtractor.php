<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB;

use BenTools\HostnameExtractor\HostnameExtractor;
use Psr\Http\Message\UriInterface;
use function BenTools\UriFactory\Helper\canonicalize;
use function Stringy\create as s;

final class ExpressionExtractor
{
    /**
     * @var HostnameExtractor
     */
    private $hostnameExtractor;

    /**
     * ExpressionExtractor constructor.
     */
    public function __construct(HostnameExtractor $hostnameExtractor)
    {
        $this->hostnameExtractor = $hostnameExtractor;
    }

    /**
     * @param HostnameExtractor $hostnameExtractor
     * @param UriInterface      $uri
     * @return iterable
     */
    public function extract(UriInterface ...$uris): iterable
    {
        $expressions = [];
        $generator = function (HostnameExtractor $hostnameExtractor, UriInterface $uri) use (&$generator) {
            $uri = canonicalize($uri)->withScheme('http');
            $path = s($uri->getPath())->trim('/');

            // a.b.c/1/2.html?param=1
            yield (string) s($uri)->removeLeft('http://');

            // a.b.c/1/2.html
            yield (string) s($uri->withQuery(''))->removeLeft('http://');

            // a.b.c/
            yield (string) s($uri->withPath('')->withQuery(''))->removeLeft('http://')->ensureRight('/');

            // a.b.c/1/
            for ($pos = 1, $items = explode('/', $path), $stack = [], $max = count($items);
                $pos < $max;
                $pos++) {
                $stack[] = array_shift($items);
                yield (string) s($uri->withQuery('')->withPath(implode('/', $stack)))->removeLeft('http://')->ensureRight('/');
            }

            $domain = $hostnameExtractor->extract($uri->getHost());

            if (null !== $domain->getSubdomain()) {
                $subDomainParts = array_slice(explode('.', $domain->getSubdomain()), -5, 5);
                array_shift($subDomainParts);

                $expressions = $generator(
                    $hostnameExtractor,
                    $uri->withHost(
                        implode('.', array_filter([
                            implode('.', $subDomainParts),
                            $domain->getSuffixedDomain()
                        ]))
                    )
                );

                foreach ($expressions as $expression) {
                    yield $expression;
                }
            }
        };
        foreach ($uris as $uri) {
            $expressions = array_merge($expressions, iterator_to_array($generator($this->hostnameExtractor, $uri)));
        }
        $expressions = array_unique($expressions);
        $expressions = array_values($expressions);
        return $expressions;
    }
}
