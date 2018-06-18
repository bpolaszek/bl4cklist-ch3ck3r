<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\ABP\Spec;

use BenTools\Bl4cklistCh3ck3r\ABP\Spec\UriMatcherSpec;
use function BenTools\UriFactory\Helper\uri;
use PHPUnit\Framework\TestCase;

class UriMatcherSpecTest extends TestCase
{

    public function testShouldMatch()
    {
        $spec = new UriMatcherSpec(uri('http://example.org/&foo=bar'), 'example.org/&foo=bar');
        $this->assertTrue($spec->isSatisfied());
    }

    public function testShouldNotMatch()
    {
        $spec = new UriMatcherSpec(uri('http://examplez.org/&foo=bar'), 'example.org/&foo=bar');
        $this->assertFalse($spec->isSatisfied());
    }

}
