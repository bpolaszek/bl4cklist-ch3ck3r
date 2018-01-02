<?php

namespace BenTools\Bl4cklistCh3ck3r\Stringy;

use SubStringy\SubStringyTrait;

class Stringy extends \Stringy\Stringy
{
    use SubStringyTrait;

    /**
     * @param string $delimiter
     * @return array
     */
    public function explode(string $delimiter): array
    {
        return 0 === count($this) ? [] : array_map(function (string $string) {
            return static::create($string, $this->encoding);
        }, explode($delimiter, (string) $this));
    }

    /**
     * @param $pattern
     * @return bool
     */
    public function matches($pattern): bool
    {
        return (bool) preg_match($pattern, (string) $this);
    }
}