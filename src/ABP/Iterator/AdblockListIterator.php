<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Iterator;

final class AdblockListIterator
{

    public function __invoke(string $content): iterable
    {
        $tok = strtok($content, "\r\n");
        while (false !== $tok) {
            $line = $tok;
            $tok = strtok("\n\r");

            if (in_array($line[0], ['[', '!'])) {
                continue;
            }

            yield $line;
        }
    }
}
