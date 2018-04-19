<?php

namespace BenTools\Bl4cklistCh3ck3r\GSB\Model;

use Throwable;

class ThreatListUpdateException extends \Exception
{
    /**
     * @var ThreatList
     */
    private $threatList;

    /**
     * @inheritDoc
     */
    public function __construct(ThreatList $threatList, string $message = null, int $code = 0, Throwable $previous = null)
    {
        $this->threatList = $threatList;
        parent::__construct($message ?? vsprintf('Error updating threatlist %s / %s / %s', [
            $threatList->getThreatType(),
            $threatList->getThreatEntryType(),
            $threatList->getPlatformType(),
        ]), $code, $previous);
    }

    /**
     * @return ThreatList
     */
    public function getThreatList(): ThreatList
    {
        return $this->threatList;
    }
}
