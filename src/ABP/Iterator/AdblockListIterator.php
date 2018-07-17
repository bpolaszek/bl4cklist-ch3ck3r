<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Iterator;

use BenTools\Bl4cklistCh3ck3r\ABP\Parser\AdblockParser;

final class AdblockListIterator
{
    /**
     * @var AdblockParser
     */
    private $parser;

    /**
     * AdblockListIterator constructor.
     * @param AdblockParser $parser
     */
    public function __construct(AdblockParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @param string $content
     * @return iterable
     */
    public function __invoke(string $content): iterable
    {
        $parse = $this->parser;
        $tok = strtok($content, "\r\n");
        while (false !== $tok) {
            $line = $tok;
            $tok = strtok("\n\r");

            if (in_array($line[0], ['[', '!'])) {
                continue;
            }

            yield $parse($line);
        }
    }
}
