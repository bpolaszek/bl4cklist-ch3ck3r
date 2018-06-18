<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\ABP;

use BenTools\Bl4cklistCh3ck3r\ABP\Parser\AdblockParser;
use BenTools\Bl4cklistCh3ck3r\ABP\Spec\RuleSpecBuilder;
use BenTools\HostnameExtractor\HostnameExtractor;
use BenTools\HostnameExtractor\SuffixProvider\SuffixProviderInterface;
use function BenTools\UriFactory\Helper\uri;
use PHPUnit\Framework\TestCase;

class FunctionnalTest extends TestCase
{

    /**
     * @var RuleSpecBuilder
     */
    private $builder;

    /**
     * @var AdblockParser
     */
    private $parser;

    public function setUp()
    {

        $this->parser = new AdblockParser();
        $this->builder = new RuleSpecBuilder(new HostnameExtractor(new class implements SuffixProviderInterface
        {
            /**
             * @inheritDoc
             */
            public function getSuffixes(): iterable
            {
                yield '.com';
            }

        }));
    }

    public function testExactMatch()
    {
        $parse = $this->parser;
        $rule = $parse('|http://example.com/|');
        $dataset = [
            'http://example.com/' => true,
            'http://example.com/foo.gif' => false,
            'http://example.info/redirect/http://example.com/' => false,
        ];
        foreach ($dataset as $url => $shouldMatch) {
            $spec = $this->builder->getSpecification(uri($url), [], $rule);
            $this->assertEquals($shouldMatch, $spec->isSatisfied());
        }
    }

    public function testDomainMatch()
    {
        $parse = $this->parser;
        $rule = $parse('||ads.example.com^');
        $dataset = [
            'http://ads.example.com/foo.gif' => true,
            'http://server1.ads.example.com/foo.gif' => true,
            'https://ads.example.com:8000/' => true,
            'http://ads.example.com.ua/foo.gif' => false,
            'http://example.com/redirect/http://ads.example.com/' => false,
        ];
        foreach ($dataset as $url => $shouldMatch) {
            $spec = $this->builder->getSpecification(uri($url), [], $rule);
            $this->assertEquals($shouldMatch, $spec->isSatisfied());
        }
    }

    public function testQueryStringMatch()
    {
        $parse = $this->parser;
        $rule = $parse('&adsourceid=');

        $dataset = [
            'http://ads.example.com/?foo=bar&adsourceid=baz' => true,
            'http://ads.example.com/?adsourceid=baz' => false,
        ];
        foreach ($dataset as $url => $shouldMatch) {
            $spec = $this->builder->getSpecification(uri($url), [], $rule);
            $this->assertEquals($shouldMatch, $spec->isSatisfied());
        }
    }


//    public function testAddressParts()
//    {
//        $parse = $this->parser;
//        $rule = $parse('/banner/*/img^');
//        $dataset = [
//            'http://example.com/banner/foo/img' => true,
//            'http://example.com/banner/foo/bar/img?param' => true,
//            'http://example.com/banner//img/foo' => true,
//            'http://example.com/banner/img' => false,
//            'http://example.com/banner/foo/imgraph' => false,
//            'http://example.com/banner/foo/img.gif' => false,
//        ];
//        foreach ($dataset as $url => $shouldMatch) {
//            $spec = $this->builder->getSpecification(uri($url), [], $rule);
//            $this->assertEquals($shouldMatch, $spec->isSatisfied());
//        }
//    }

}
