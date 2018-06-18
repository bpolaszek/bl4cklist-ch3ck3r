<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\ABP\Spec;

use BenTools\Bl4cklistCh3ck3r\ABP\Spec\DomainNameMatcherSpec;
use BenTools\HostnameExtractor\HostnameExtractor;
use BenTools\HostnameExtractor\SuffixProvider\SuffixProviderInterface;
use PHPUnit\Framework\TestCase;

class DomainNameMatcherSpecTest extends TestCase
{

    /**
     * @var HostnameExtractor
     */
    private $hostnameExtractor;

    public function setUp()
    {
        $suffixProvider = new class implements SuffixProviderInterface
        {
            /**
             * @inheritDoc
             */
            public function getSuffixes(): iterable
            {
                yield '.com';
                yield '.info';
            }

        };
        $this->hostnameExtractor = new HostnameExtractor($suffixProvider);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testDomainMatcher(string $hostname, bool $shouldMatchSubdomains, array $shouldMatch, array $shouldNotMatch)
    {
        $rule = $this->hostnameExtractor->extract($hostname);
        foreach ($shouldMatch as $domain) {
            $spec = new DomainNameMatcherSpec($this->hostnameExtractor->extract($domain), $rule, $shouldMatchSubdomains);
            $this->assertTrue($spec->isSatisfied());
        }
        foreach ($shouldNotMatch as $domain) {
            $spec = new DomainNameMatcherSpec($this->hostnameExtractor->extract($domain), $rule, $shouldMatchSubdomains);
            $this->assertFalse($spec->isSatisfied());
        }
    }

    public function dataProvider()
    {
        yield ['example.com', true, ['example.com', 'ads.example.com'], ['example.info', 'ads.example.info']];
        yield ['ads.example.com', true, ['ads.example.com', 'foo.ads.example.com'], ['example.com', 'foo.example.com']];
        yield ['ads.example.com', false, ['ads.example.com'], ['example.com', 'foo.example.com', 'foo.ads.example.com']];
    }

}
