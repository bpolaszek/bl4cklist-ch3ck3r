<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\ABP\Spec;

use BenTools\Bl4cklistCh3ck3r\ABP\Spec\RequestUriMatcherSpec;
use PHPUnit\Framework\TestCase;
use function BenTools\UriFactory\Helper\uri;

class RequestUriMatcherSpecTest extends TestCase
{

    public function testShouldMatch()
    {
        $spec = new RequestUriMatcherSpec(uri('http://example.org/&foo=bar'), '&foo=bar');
        $this->assertTrue($spec->isSatisfied());
    }

    public function testShouldAlsoMatch()
    {
        $spec = new RequestUriMatcherSpec(uri('http://examplez.org/&foo=bar'), '&foo=bar');
        $this->assertTrue($spec->isSatisfied());
    }

    public function testShouldNotMatch()
    {
        $spec = new RequestUriMatcherSpec(uri('http://examplez.org/&foo=baz'), '&foo=bar');
        $this->assertFalse($spec->isSatisfied());
    }

}
