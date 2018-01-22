<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB;

use BenTools\Bl4cklistCh3ck3r\GSB\Model\BlacklistedState;
use BenTools\Bl4cklistCh3ck3r\GSB\Model\Hash;
use BenTools\Bl4cklistCh3ck3r\GSB\Model\ThreatList;
use BenTools\Bl4cklistCh3ck3r\GSB\Model\ThreatListUpdateException;
use BenTools\Bl4cklistCh3ck3r\GSB\Model\ThrottleException;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\Hashes\HashStorageInterface;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\State\StateStorageInterface;
use BenTools\Bl4cklistCh3ck3r\GSB\Storage\Throttle\ThrottleStorageInterface;
use function BenTools\FlattenIterator\flatten;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use function GuzzleHttp\Psr7\str;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function BenTools\QueryString\query_string;
use function GuzzleHttp\Psr7\stream_for;
use Psr\Http\Message\UriInterface;

class GSBClient
{

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $apiKey;

    /**
     * @inheritDoc
     */
    public function __construct(Client $client, string $apiKey)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    /**
     * @param RequestInterface $request
     * @return RequestInterface
     * @throws \InvalidArgumentException
     */
    public function authenticateRequest(RequestInterface $request): RequestInterface
    {
        $uri = $request->getUri();
        $qs = query_string($uri);
        return $request->withUri(
            $uri->withQuery(
                (string) $qs->withParam('key', $this->apiKey)
            )
        );
    }

    /**
     * @return ThreatList[]
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function getThreatLists(): array
    {
        $request = new Request('GET', 'https://safebrowsing.googleapis.com/v4/threatLists');
        return $this->client
            ->sendAsync($this->authenticateRequest($request))
            ->then(function (ResponseInterface $response) {
                return \GuzzleHttp\json_decode($response->getBody(), true);
            })
            ->then(function (array $json) {
                return iterable_to_array(ThreatList::fromJsonList($json['threatLists']));
            })
            ->wait();
    }

    /**
     * @param HashStorageInterface     $hashStorage
     * @param StateStorageInterface    $stateStorage
     * @param ThrottleStorageInterface $throttleStorage
     * @param ThreatList[]             ...$threatLists
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \RuntimeException
     */
    public function updateDatabase(HashStorageInterface $hashStorage, StateStorageInterface $stateStorage, ThrottleStorageInterface $throttleStorage, ThreatList ... $threatLists)
    {
        if (0 === count($threatLists)) {
            throw new \InvalidArgumentException('No threatlist provided.');
        }
        if (0 !== ($remainingDuration = $throttleStorage->getRemainingDuration())) {
            throw new ThrottleException(sprintf('We must wait %s seconds.', $remainingDuration));
        }
        $request = new Request('POST', 'https://safebrowsing.googleapis.com/v4/threatListUpdates:fetch');
        $threatLists = array_map(function (ThreatList $threatList) use ($stateStorage) {
            return $threatList->withState(
                $stateStorage->getState(
                    $threatList->getThreatType(),
                    $threatList->getThreatEntryType(),
                    $threatList->getPlatformType()
                )
            )
                ->withConstraints([
                    'supportedCompressions' => ['RAW']
                ]);
        }, $threatLists);

        $request = $request->withBody(
            stream_for(
                \GuzzleHttp\json_encode([
                    'listUpdateRequests' => $threatLists
                ])
            )
        );

        $updates = $this->client->sendAsync($this->authenticateRequest($request))->then(function (ResponseInterface $response) use ($throttleStorage) {
            $json = \GuzzleHttp\json_decode($response->getBody(), true);
            $throttleStorage->setRemainingDuration((int) ceil((float) ($json['minimumWaitDuration'] ?? 0)));
            return $json['listUpdateResponses'] ?? [];
        })->wait();

        foreach ($updates as $u => $update) {
            $threatType = $update['threatType'];
            $threatEntryType = $update['threatEntryType'];
            $platformType = $update['platformType'];
            $newClientState = $update['newClientState'];
            $checksum = $update['checksum']['sha256'];
            $isFullUpdate = 'FULL_UPDATE' === $update['responseType'];
            $removals = $update['removals'][0]['rawIndices']['indices'] ?? [];
            $additions = [];

            $hashStorage->beginTransaction();

            if ($isFullUpdate) {
                $hashStorage->clearHashes($threatType, $threatEntryType, $platformType);
            }

            foreach ($update['additions'] ?? [] as $addition) {
                $additions = array_merge($additions, iterable_to_array(Hash::fromBase64($addition['rawHashes']['rawHashes'])->getSplitHashes($addition['rawHashes']['prefixSize'])));
            }

            $hashStorage->storeHashes($threatType, $threatEntryType, $platformType, $additions, $removals);
            $hashStorage->commit();

            // Verify checksum
            if ($checksum !== $hashStorage->getCheckSum($threatType, $threatEntryType, $platformType)->toBase64()) {
                throw new ThreatListUpdateException(new ThreatList($threatType, $threatEntryType, $platformType));
            }

            $stateStorage->setState($threatType, $threatEntryType, $platformType, $newClientState);
        }
    }

    /**
     * @param HashStorageInterface $hashStorage
     * @param array|ThreatList[]   $threatLists
     * @param array|string[]       $expressions
     * @return array
     */
    public function matchesLocalDatabase(HashStorageInterface $hashStorage, array $threatLists, array $expressions): array
    {
        /**
         * @var Hash[]             $hashes
         * @var string[]           $expressions
         * @var ThreatList[]       $threatLists
         * @var BlacklistedState[] $states
         */
        $checkList = [];

        $expressions = (function (string ...$expressions) {
            return $expressions;
        })(...$expressions);

        $threatLists = (function (ThreatList ...$threatLists) {
            return $threatLists;
        })(...$threatLists);

        foreach ($threatLists as $threatList) {
            $prefixSizes = $hashStorage->getPrefixSizes($threatList->getThreatType(), $threatList->getThreatEntryType(), $threatList->getPlatformType());
            foreach ($prefixSizes as $prefixSize) {
                foreach ($expressions as $expression) {
                    $checkList[$expression][] = new BlacklistedState($expression, Hash::fromUnhashedString($expression), $prefixSize, $threatList);
                }
            }
        }

        foreach ($checkList as $expression => $states) {
            foreach ($states as $state) {
                $threatList = $state->getThreatList();
                $hash = $state->getHash();
                if ($hashStorage->containsHash($threatList->getThreatType(), $threatList->getThreatEntryType(), $threatList->getPlatformType(), $hash->shorten($state->getLength() * 2))) {
                    $state->setShouldBeChecked(true);
                }
            }
        }

        return $checkList;
    }

    /**
     * @param ThrottleStorageInterface $throttleStorage
     * @param StateStorageInterface    $stateStorage
     * @param array                    $checkList
     * @return array
     * @throws ThrottleException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function findFullHashes(ThrottleStorageInterface $throttleStorage, StateStorageInterface $stateStorage, array &$checkList): void
    {
        if (0 !== ($remainingDuration = $throttleStorage->getRemainingDuration())) {
            throw new ThrottleException(sprintf('We must wait %s seconds.', $remainingDuration));
        }

        $threatTypes = [];
        $threatEntryTypes = [];
        $platformTypes = [];

        /** @var BlacklistedState[] $states */
        foreach ($checkList as $expression => $states) {
            foreach ($states as $state) {
                if ($state->shouldBeChecked() && !in_array($state->getThreatList()->getThreatType(), $threatTypes)) {
                    $threatTypes[] = $state->getThreatList()->getThreatType();
                }
                if ($state->shouldBeChecked() && !in_array($state->getThreatList()->getThreatEntryType(), $threatEntryTypes)) {
                    $threatEntryTypes[] = $state->getThreatList()->getThreatEntryType();
                }
                if ($state->shouldBeChecked() && !in_array($state->getThreatList()->getPlatformType(), $platformTypes)) {
                    $platformTypes[] = $state->getThreatList()->getPlatformType();
                }
            }
        }

        $threatEntries = array_map(function (BlacklistedState $state) {
            return $state->shouldBeChecked() ? ['hash' => $state->getHash()->shorten($state->getLength() * 2)->toBase64()] : null;
        }, flatten($checkList)->asArray());
        $threatEntries = array_values(array_unique(array_filter($threatEntries)));

        $request = new Request('POST', 'https://safebrowsing.googleapis.com/v4/fullHashes:find');
        $request = $request->withBody(
            stream_for(
                \GuzzleHttp\json_encode([
                    'clientStates' => array_values($stateStorage->getAllStates()),
                    'threatInfo'   => [
                        'threatTypes'      => $threatTypes,
                        'threatEntryTypes' => $threatEntryTypes,
                        'platformTypes'    => $platformTypes,
                        'threatEntries'    => $threatEntries,
                    ],
                ])
            )
        );

        $matches = $this->client->sendAsync($this->authenticateRequest($request))
            ->then(function (ResponseInterface $response) use ($throttleStorage) {
                $json = \GuzzleHttp\json_decode($response->getBody(), true);
                $throttleStorage->setRemainingDuration((int) ceil((float) ($json['minimumWaitDuration'] ?? 0)));
                return $json['matches'] ?? [];
            })
            ->wait();

        foreach ($matches as $match) {
            $fullHash = Hash::fromBase64($match['threat']['hash']);

            foreach ($checkList as $states) {
                foreach ($states as $state) {
                    if ($fullHash->toBase64() === $state->getHash()->toBase64()
                        && $match['threatType'] === $state->getThreatList()->getThreatType()
                        && $match['threatEntryType'] === $state->getThreatList()->getThreatEntryType()
                        && $match['platformType'] === $state->getThreatList()->getPlatformType()) {
                        $state->setBlacklisted(true);
                    }
                }
            }
        }
    }

    /**
     * @param HashStorageInterface     $hashStorage
     * @param ThrottleStorageInterface $throttleStorage
     * @param StateStorageInterface    $stateStorage
     * @param array                    $threatLists
     * @param array                    $expressions
     * @return array
     * @throws ThrottleException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function getExpressionsBlacklistedStates(HashStorageInterface $hashStorage, ThrottleStorageInterface $throttleStorage, StateStorageInterface $stateStorage, array $threatLists, array $expressions): array
    {
        $checkList = $this->matchesLocalDatabase($hashStorage, $threatLists, $expressions);
        $this->findFullHashes($throttleStorage, $stateStorage, $checkList);
        return $checkList;
    }

    /**
     * @param HashStorageInterface     $hashStorage
     * @param ThrottleStorageInterface $throttleStorage
     * @param StateStorageInterface    $stateStorage
     * @param ExpressionExtractor      $expressionExtractor
     * @param array                    $threatLists
     * @param UriInterface             $uri
     * @return bool
     * @throws ThrottleException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function isUrlBlackListed(HashStorageInterface $hashStorage, ThrottleStorageInterface $throttleStorage, StateStorageInterface $stateStorage, ExpressionExtractor $expressionExtractor, array $threatLists, UriInterface $uri): bool
    {
        /** @var BlacklistedState[] $states */
        $expressions = $expressionExtractor->extract($uri);
        $checkList = $this->getExpressionsBlacklistedStates($hashStorage, $throttleStorage, $stateStorage, $threatLists, $expressions);
        foreach ($checkList as $expression => $states) {
            foreach ($states as $state) {
                if ($state->isBlacklisted()) {
                    return true;
                }
            }
        }
        return false;
    }
}
