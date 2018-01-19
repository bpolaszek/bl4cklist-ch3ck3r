<?php

namespace BenTools\Bl4cklistCh3ck3r\Tests\GSB;

use BenTools\Bl4cklistCh3ck3r\GSB\ExpressionExtractor;
use BenTools\Bl4cklistCh3ck3r\GSB\GSBClient;
use BenTools\Bl4cklistCh3ck3r\GSB\Model\BlacklistedState;
use BenTools\Bl4cklistCh3ck3r\GSB\Model\ThreatList;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes\HashStorageInterface;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes\PSR16HashStorage;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\State\PSR16StateStorage;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\Throttle\PSR16ThrottleStorage;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\State\StateStorageInterface;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\Throttle\ThrottleStorageInterface;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\Throttle\NullThrottleStorage;
use BenTools\HostnameExtractor\HostnameExtractor;
use BenTools\HostnameExtractor\SuffixProvider\PublicSuffixProvider;
use function BenTools\UriFactory\Helper\uri;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Cache\Simple\ArrayCache;

class GSBClientTest extends TestCase
{

    private static $hashStorage;
    private static $stateStorage;
    private static $throttleStorage;

    public function testAuthentication()
    {
        $reflection = new \ReflectionClass(GSBClient::class);
        $method = $reflection->getMethod('authenticateRequest');
        $method->setAccessible(true);

        $gsb = new GSBClient($this->mockGuzzleClient(), 'foo');
        $request = new Request('GET', 'https://www.example.org/');
        $authenticatedRequest = $method->invoke($gsb, $request);
        $this->assertEquals('https://www.example.org/?key=foo', (string) $authenticatedRequest->getUri());
    }

    public function testGetThreatLists()
    {
        $guzzle = $this->mockGuzzleClient(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/threatLists.json')));
        $gsb = new GSBClient($guzzle, 'foo');
        $threatLists = $gsb->getThreatLists();
        $this->assertEquals(25, count($threatLists));
        $this->assertInstanceOf(ThreatList::class, $threatLists[0]);
    }

    public function testImport()
    {
        $hashStorage = $this->getHashStorage();
        $stateStorage = $this->getStateStorage();
        $throttleStorage = $this->getThrottleStorage();

        $gsb = new GSBClient($this->mockGuzzleClient(...[
            new Response(200, [], file_get_contents(__DIR__ . '/fixtures/threatLists.json')),
            new Response(200, [], file_get_contents(__DIR__ . '/fixtures/full.json'))
        ]), 'foo');

        $threatLists = $gsb->getThreatLists();
        $gsb->updateDatabase($hashStorage, $stateStorage, $throttleStorage, ...$threatLists);

        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'ANY_PLATFORM'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'WINDOWS'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'LINUX'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'OSX'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'ALL_PLATFORMS'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'CHROME'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'ANY_PLATFORM'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'WINDOWS'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'LINUX'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'OSX'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'ALL_PLATFORMS'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'CHROME'));
        $this->assertEquals('Cg0IBBADGAEiAzAwMTABEOujAhoCGAPUeTcs', $stateStorage->getState('POTENTIALLY_HARMFUL_APPLICATION', 'URL', 'ANDROID'));
        $this->assertEquals('Cg0IBBADGAEiAzAwMTABEOujAhoCGAPUeTcs', $stateStorage->getState('MALWARE', 'URL', 'IOS'));
        $this->assertEquals('Cg0IBBADGAEiAzAwMTABEOujAhoCGAPUeTcs', $stateStorage->getState('POTENTIALLY_HARMFUL_APPLICATION', 'URL', 'IOS'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'ANY_PLATFORM'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'WINDOWS'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'LINUX'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'OSX'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'ALL_PLATFORMS'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'CHROME'));
        $this->assertEquals('Cg0IAxADGAEiAzAwMTABEAEaAhgDfg578g==', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'ANDROID'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTADEBQaAhgD7k3BFg==', $stateStorage->getState('MALWARE', 'IP_RANGE', 'WINDOWS'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTADEBQaAhgD7k3BFg==', $stateStorage->getState('MALWARE', 'IP_RANGE', 'LINUX'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTADEBQaAhgD7k3BFg==', $stateStorage->getState('MALWARE', 'IP_RANGE', 'OSX'));
    }

    /**
     * @expectedException \BenTools\Bl4cklistCh3ck3r\GSB\Model\ThrottleException
     */
    public function testUpdateFailsWhenNotRespectingMinimumDuration()
    {

        $hashStorage = $this->getHashStorage();
        $stateStorage = $this->getStateStorage();
        $throttleStorage = $this->getThrottleStorage();

        $gsb = new GSBClient($this->mockGuzzleClient(...[
            new Response(200, [], file_get_contents(__DIR__ . '/fixtures/threatLists.json')),
            new Response(200, [], file_get_contents(__DIR__ . '/fixtures/partial.json'))
        ]), 'foo');

        $threatLists = $gsb->getThreatLists();
        $gsb->updateDatabase($hashStorage, $stateStorage, $throttleStorage, ...$threatLists);
    }

    public function testUpdateSucceedsWhenRespectingMinimumDuration()
    {
        $hashStorage = $this->getHashStorage();
        $stateStorage = $this->getStateStorage();
        $throttleStorage = new NullThrottleStorage();

        $gsb = new GSBClient($this->mockGuzzleClient(...[
            new Response(200, [], file_get_contents(__DIR__ . '/fixtures/threatLists.json')),
            new Response(200, [], file_get_contents(__DIR__ . '/fixtures/partial.json'))
        ]), 'foo');

        $threatLists = $gsb->getThreatLists();

        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'ANY_PLATFORM'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'WINDOWS'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'LINUX'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'OSX'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'ALL_PLATFORMS'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABELS4AxoCGAPfpZUX', $stateStorage->getState('MALWARE', 'URL', 'CHROME'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'ANY_PLATFORM'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'WINDOWS'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'LINUX'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'OSX'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'ALL_PLATFORMS'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEMe/AxoCGAMMf9Ze', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'CHROME'));
        $this->assertEquals('Cg0IBBADGAEiAzAwMTABEOujAhoCGAPUeTcs', $stateStorage->getState('POTENTIALLY_HARMFUL_APPLICATION', 'URL', 'ANDROID'));
        $this->assertEquals('Cg0IBBADGAEiAzAwMTABEOujAhoCGAPUeTcs', $stateStorage->getState('MALWARE', 'URL', 'IOS'));
        $this->assertEquals('Cg0IBBADGAEiAzAwMTABEOujAhoCGAPUeTcs', $stateStorage->getState('POTENTIALLY_HARMFUL_APPLICATION', 'URL', 'IOS'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'ANY_PLATFORM'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'WINDOWS'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'LINUX'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'OSX'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'ALL_PLATFORMS'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEM6nAxoCGAOCcMsP', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'CHROME'));
        $this->assertEquals('Cg0IAxADGAEiAzAwMTABEAEaAhgDfg578g==', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'ANDROID'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTADEBQaAhgD7k3BFg==', $stateStorage->getState('MALWARE', 'IP_RANGE', 'WINDOWS'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTADEBQaAhgD7k3BFg==', $stateStorage->getState('MALWARE', 'IP_RANGE', 'LINUX'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTADEBQaAhgD7k3BFg==', $stateStorage->getState('MALWARE', 'IP_RANGE', 'OSX'));

        $gsb->updateDatabase($hashStorage, $stateStorage, $throttleStorage, ...$threatLists);

        $this->assertEquals('Cg0IARAGGAEiAzAwMTABEPq9AxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDLqIfJQ==', $stateStorage->getState('MALWARE', 'URL', 'ANY_PLATFORM'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABEPq9AxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDLqIfJQ==', $stateStorage->getState('MALWARE', 'URL', 'WINDOWS'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABEPq9AxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDLqIfJQ==', $stateStorage->getState('MALWARE', 'URL', 'LINUX'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABEPq9AxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDLqIfJQ==', $stateStorage->getState('MALWARE', 'URL', 'OSX'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABEPq9AxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDLqIfJQ==', $stateStorage->getState('MALWARE', 'URL', 'ALL_PLATFORMS'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTABEPq9AxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDLqIfJQ==', $stateStorage->getState('MALWARE', 'URL', 'CHROME'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEKnFAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDz1pmmg==', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'ANY_PLATFORM'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEKnFAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDz1pmmg==', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'WINDOWS'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEKnFAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDz1pmmg==', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'LINUX'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEKnFAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDz1pmmg==', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'OSX'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEKnFAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDz1pmmg==', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'ALL_PLATFORMS'));
        $this->assertEquals('Cg0IAhAGGAEiAzAwMTABEKnFAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDz1pmmg==', $stateStorage->getState('SOCIAL_ENGINEERING', 'URL', 'CHROME'));
        $this->assertEquals('Cg0IBBADGAEiAzAwMTABEO6lAhoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDK5Ys4Q==', $stateStorage->getState('POTENTIALLY_HARMFUL_APPLICATION', 'URL', 'ANDROID'));
        $this->assertEquals('Cg0IBBADGAEiAzAwMTABEO6lAhoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDK5Ys4Q==', $stateStorage->getState('MALWARE', 'URL', 'IOS'));
        $this->assertEquals('Cg0IBBADGAEiAzAwMTABEO6lAhoVCMCbttXlw4LfMhCM5ayK8ZXYAhgDK5Ys4Q==', $stateStorage->getState('POTENTIALLY_HARMFUL_APPLICATION', 'URL', 'IOS'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEMasAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgD0B1kog==', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'ANY_PLATFORM'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEMasAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgD0B1kog==', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'WINDOWS'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEMasAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgD0B1kog==', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'LINUX'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEMasAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgD0B1kog==', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'OSX'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEMasAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgD0B1kog==', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'ALL_PLATFORMS'));
        $this->assertEquals('Cg0IAxAGGAEiAzAwMTABEMasAxoVCMCbttXlw4LfMhCM5ayK8ZXYAhgD0B1kog==', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'CHROME'));
        $this->assertEquals('Cg0IAxADGAEiAzAwMTABEAEaFQjAm7bV5cOC3zIQjOWsivGV2AIYA1FH2Rg=', $stateStorage->getState('UNWANTED_SOFTWARE', 'URL', 'ANDROID'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTADEBQaFQjAm7bV5cOC3zIQjOWsivGV2AIYA2CFWTY=', $stateStorage->getState('MALWARE', 'IP_RANGE', 'WINDOWS'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTADEBQaFQjAm7bV5cOC3zIQjOWsivGV2AIYA2CFWTY=', $stateStorage->getState('MALWARE', 'IP_RANGE', 'LINUX'));
        $this->assertEquals('Cg0IARAGGAEiAzAwMTADEBQaFQjAm7bV5cOC3zIQjOWsivGV2AIYA2CFWTY=', $stateStorage->getState('MALWARE', 'IP_RANGE', 'OSX'));
    }

    public function testCheckExpressions()
    {
        $hashStorage = $this->getHashStorage();
        $gsb = new GSBClient($this->mockGuzzleClient(new Response(200, [], file_get_contents(__DIR__ . '/fixtures/check.json'))), 'foo');
        $threatLists = [new ThreatList('SOCIAL_ENGINEERING', 'URL', 'ALL_PLATFORMS')];
        $states = $gsb->matchesLocalDatabase($hashStorage, $threatLists, [
            'testsafebrowsing.appspot.com/s/phishing.html',
            'google.com/',
        ]);
        $this->assertCount(2, $states);
        $this->assertArrayHasKey('testsafebrowsing.appspot.com/s/phishing.html', $states);
        $this->assertInternalType('array', $states['testsafebrowsing.appspot.com/s/phishing.html']);
        $this->assertInstanceOf(BlacklistedState::class, $states['testsafebrowsing.appspot.com/s/phishing.html'][0]);
        $this->assertArrayHasKey('google.com/', $states);
        $this->assertInternalType('array', $states['google.com/']);
        $this->assertInstanceOf(BlacklistedState::class, $states['google.com/'][0]);

        $this->assertTrue($states['testsafebrowsing.appspot.com/s/phishing.html'][0]->shouldBeChecked());
        $this->assertFalse($states['google.com/'][0]->shouldBeChecked());

        $gsb->findFullHashes(new NullThrottleStorage(), $this->getStateStorage(), $states);
        $this->assertTrue($states['testsafebrowsing.appspot.com/s/phishing.html'][0]->isBlacklisted());
        $this->assertFalse($states['google.com/'][0]->isBlacklisted());
    }

    public function testIsUrlBlacklisted()
    {
        $hashStorage = $this->getHashStorage();
        $throttleStorage = new NullThrottleStorage();
        $stateStorage = $this->getStateStorage();
        $expressionExtractor = new ExpressionExtractor(new HostnameExtractor(new PublicSuffixProvider(new Client())));
        $gsb = new GSBClient($this->mockGuzzleClient(...[
            new Response(200, [], file_get_contents(__DIR__ . '/fixtures/check.json')),
            new Response(200, [], file_get_contents(__DIR__ . '/fixtures/check.json')),
        ]), 'foo');
        $threatLists = [new ThreatList('SOCIAL_ENGINEERING', 'URL', 'ALL_PLATFORMS')];
        $this->assertTrue($gsb->isUrlBlackListed($hashStorage, $throttleStorage, $stateStorage, $expressionExtractor, $threatLists, uri('http://testsafebrowsing.appspot.com/s/phishing.html')));
        $this->assertFalse($gsb->isUrlBlackListed($hashStorage, $throttleStorage, $stateStorage, $expressionExtractor, $threatLists, uri('http://www.google.fr')));
    }

    /**
     * @param ResponseInterface[] ...$responses
     * @return Client
     */
    protected function mockGuzzleClient(ResponseInterface ...$responses): Client
    {
        $stack = new HandlerStack();
        $stack->setHandler(new MockHandler($responses));
        return new Client(['handler' => $stack]);
    }



    /**
     * @return HashStorageInterface
     */
    protected function getHashStorage(): HashStorageInterface
    {
        if (null === self::$hashStorage) {
            self::$hashStorage = new PSR16HashStorage(new ArrayCache());
        }
        return self::$hashStorage;
    }

    /**
     * @return StateStorageInterface
     */
    protected function getStateStorage(): StateStorageInterface
    {
        if (null === self::$stateStorage) {
            self::$stateStorage = new PSR16StateStorage(new ArrayCache());
        }
        return self::$stateStorage;
    }

    /**
     * @return ThrottleStorageInterface
     */
    protected function getThrottleStorage(): ThrottleStorageInterface
    {
        if (null === self::$throttleStorage) {
            self::$throttleStorage = new PSR16ThrottleStorage(new ArrayCache());
        }
        return self::$throttleStorage;
    }

}
