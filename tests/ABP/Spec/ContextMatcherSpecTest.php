<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\ABP\Spec;

use BenTools\Bl4cklistCh3ck3r\ABP\Parser\AdblockParser;
use BenTools\Bl4cklistCh3ck3r\ABP\Spec\ContextMatcherSpec;
use PHPUnit\Framework\TestCase;

class ContextMatcherSpecTest extends TestCase
{

    public function testMatchContext()
    {
        $spec = new ContextMatcherSpec([], ['options' => ['popup' => AdblockParser::APPLY_FILTER]]);
        $this->assertTrue($spec->isSatisfied());

        $spec = new ContextMatcherSpec(['popup' => AdblockParser::APPLY_FILTER], ['options' => ['popup' => AdblockParser::APPLY_FILTER]]);
        $this->assertTrue($spec->isSatisfied());

    }

}
