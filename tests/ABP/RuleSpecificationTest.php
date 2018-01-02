<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\ABP;

use BenTools\Bl4cklistCh3ck3r\ABP\Model\Rule;
use BenTools\Bl4cklistCh3ck3r\ABP\RuleSpecificationFactory;
use function BenTools\UriFactory\Helper\uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class RuleSpecificationTest extends TestCase
{
    use SpecificationTestTrait;

    /**
     * @var RuleSpecificationFactory
     */
    private static $factory;

    public static function setUpBeforeClass()
    {
        self::$factory = new RuleSpecificationFactory();
    }

    /**
     * @dataProvider dataProvider
     *
     * @param string         $rule
     * @param UriInterface[] $shouldMatch
     * @param UriInterface[] $shouldNotMatch
     */
    public function testSpecification(string $rule, array $shouldMatch, array $shouldNotMatch = [])
    {
        $rule = Rule::createFromString($rule);
        foreach ($shouldMatch as $uri) {
            $spec = self::$factory->createSpecification($rule, $uri);
            $this->assertSpecificationFulfilled($spec);
        }
        foreach ($shouldNotMatch as $uri) {
            $spec = self::$factory->createSpecification($rule, $uri);
            $this->assertSpecificationRejected($spec);
        }
    }

    public function dataProvider()
    {
        return [
            [
                'rule'           => '|http://example.com/|',
                'shouldMatch'    => [
                    uri('http://example.com/'),
                ],
                'shouldNotMatch' => [
                    uri('http://example.com/foo.gif'),
                    uri('http://example.info/redirect/http://example.com/'),
                ],
            ],

        ];
    }

}