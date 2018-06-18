<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\ABP;

use BenTools\Bl4cklistCh3ck3r\ABP\Parser\AdblockParser;
use PHPUnit\Framework\TestCase;

class AdblockParserTest extends TestCase
{
    public function testParseOptions()
    {
        $parser = new AdblockParser();
        $rule = $parser->parse('|http://example.com/|$popup,~script,domain=extremetube.com|~keezmovies.com|pornhub.com');
        $this->assertInternalType('array', $rule);
        $this->assertInternalType('array', $rule['options']);
        $this->assertArrayHasKey('popup', $rule['options']);
        $this->assertEquals($parser::APPLY_FILTER, $rule['options']['popup']);
        $this->assertArrayHasKey('script', $rule['options']);
        $this->assertEquals($parser::IGNORE_FILTER, $rule['options']['script']);
        $this->assertArrayHasKey('domain', $rule['options']);
        $this->assertInternalType('array', $rule['options']['domain']);
        $this->assertArrayHasKey('extremetube.com', $rule['options']['domain']);
        $this->assertEquals($parser::APPLY_FILTER, $rule['options']['domain']['extremetube.com']);
        $this->assertArrayHasKey('keezmovies.com', $rule['options']['domain']);
        $this->assertEquals($parser::IGNORE_FILTER, $rule['options']['domain']['keezmovies.com']);
        $this->assertArrayHasKey('pornhub.com', $rule['options']['domain']);
        $this->assertEquals($parser::APPLY_FILTER, $rule['options']['domain']['pornhub.com']);
    }

    public function testParseExactMatch()
    {
        $parser = new AdblockParser();
        $rule = $parser->parse('|http://example.com/|');
        $this->assertInternalType('array', $rule);
        $this->assertEquals($parser::EXACT_MATCH, $rule['type']);
        $this->assertEquals('http://example.com/', $rule['pattern']);
        $this->assertEquals([], $rule['options']);
        $this->assertFalse($rule['exception']);
        $this->assertNull($rule['hostname']);
        $this->assertNull($rule['request_uri']);
    }

    public function testParseExactMatchException()
    {
        $parser = new AdblockParser();
        $rule = $parser->parse('@@|http://example.com/|');
        $this->assertInternalType('array', $rule);
        $this->assertEquals($parser::EXACT_MATCH, $rule['type']);
        $this->assertEquals('http://example.com/', $rule['pattern']);
        $this->assertEquals([], $rule['options']);
        $this->assertTrue($rule['exception']);
        $this->assertNull($rule['hostname']);
        $this->assertNull($rule['request_uri']);
    }

    public function testParseDomainMatch()
    {
        $parser = new AdblockParser();
        $rule = $parser->parse('||ads.example.com^');
        $this->assertInternalType('array', $rule);
        $this->assertEquals($parser::DOMAIN_NAME, $rule['type']);
        $this->assertEquals('ads.example.com', $rule['pattern']);
        $this->assertEquals([], $rule['options']);
        $this->assertFalse($rule['exception']);
        $this->assertEquals('ads.example.com', $rule['hostname']);
        $this->assertNull($rule['request_uri']);
    }

    public function testParseDomainMatchException()
    {
        $parser = new AdblockParser();
        $rule = $parser->parse('@@||ads.example.com^');
        $this->assertInternalType('array', $rule);
        $this->assertEquals($parser::DOMAIN_NAME, $rule['type']);
        $this->assertEquals('ads.example.com', $rule['pattern']);
        $this->assertEquals([], $rule['options']);
        $this->assertTrue($rule['exception']);
        $this->assertEquals('ads.example.com', $rule['hostname']);
        $this->assertNull($rule['request_uri']);
    }
}
