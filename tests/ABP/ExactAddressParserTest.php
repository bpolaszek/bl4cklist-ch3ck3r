<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\ABP;

use BenTools\Bl4cklistCh3ck3r\ABP\RuleParser\ExactAddressParser;
use function BenTools\UriFactory\Helper\uri;
use PHPUnit\Framework\TestCase;

class ExactAddressParserTest extends TestCase
{
    use SpecificationTestTrait;

    public function testParser()
    {
        $parser = new ExactAddressParser();

        $rule = '|http://example.com/|';
        $this->assertTrue($parser->supports($rule));
        $this->assertSpecificationFulfilled($parser->getSpecification($rule, uri('http://example.com/')));

        $rule = '|http://ex*le.com/|';
        $this->assertTrue($parser->supports($rule));
        $this->assertSpecificationFulfilled($parser->getSpecification($rule, uri('http://example.com/')));
        $this->assertSpecificationRejected($parser->getSpecification($rule, uri('http://foobar.com/')));

        $rule = '|http://ex*le.com/|$~xmlhttprequest';
        $this->assertTrue($parser->supports($rule));
        $this->assertSpecificationFulfilled($parser->getSpecification($rule, uri('http://example.com/')));
    }

}
