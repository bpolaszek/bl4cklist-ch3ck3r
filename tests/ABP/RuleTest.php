<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\ABP;

use BenTools\Bl4cklistCh3ck3r\ABP\Model\DomainOption;
use BenTools\Bl4cklistCh3ck3r\ABP\Model\Rule;
use PHPUnit\Framework\TestCase;

class RuleTest extends TestCase
{

    public function testRuleFromString()
    {
        $rule = Rule::createFromString('/banner/*/img$~third-party,domain=~blogs.technet.microsoft.com|channel4.com');
        $options = $rule->getOptions();
        $this->assertEquals(Rule::BLOCK_BY_ADDRESS_PART, $rule->getBlockingType());
        $this->assertEquals('/banner/([\w\_\-\.\%]+)/img', (string) $rule->getPattern());

        $this->assertCount(3, $options);

        $this->assertEquals('third-party', $options[0]->getType());
        $this->assertTrue($options[0]->isWhiteListed());

        $this->assertEquals('domain', $options[1]->getType());
        $this->assertInstanceOf(DomainOption::class, $options[1]);
        $this->assertEquals('blogs.technet.microsoft.com', $options[1]->getDomainName());
        $this->assertTrue($options[1]->isWhiteListed());

        $this->assertEquals('domain', $options[2]->getType());
        $this->assertInstanceOf(DomainOption::class, $options[2]);
        $this->assertEquals('channel4.com', $options[2]->getDomainName());
        $this->assertFalse($options[2]->isWhiteListed());


        $rule = Rule::createFromString('||ads.example.com$image,~object');
        $options = $rule->getOptions();
        $this->assertEquals(Rule::BLOCK_BY_DOMAIN_NAME, $rule->getBlockingType());
        $this->assertEquals('ads.example.com', (string) $rule->getPattern());

        $this->assertCount(2, $options);
        $this->assertEquals('image', $options[0]->getType());
        $this->assertFalse($options[0]->isWhiteListed());
        $this->assertEquals('object', $options[1]->getType());



        $rule = Rule::createFromString('|http://example.com/|$image,~object');
        $options = $rule->getOptions();
        $this->assertEquals(Rule::BLOCK_EXACT_ADDRESS, $rule->getBlockingType());
        $this->assertEquals('http://example.com/', (string) $rule->getPattern());

        $this->assertCount(2, $options);
        $this->assertEquals('image', $options[0]->getType());
        $this->assertFalse($options[0]->isWhiteListed());
        $this->assertEquals('object', $options[1]->getType());

    }
}
