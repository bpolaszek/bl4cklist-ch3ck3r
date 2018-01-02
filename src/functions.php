<?php

namespace BenTools\Bl4cklistCh3ck3r;

use BenTools\Bl4cklistCh3ck3r\Stringy\Stringy;

function s($str = '', $encoding = null)
{
    return new Stringy($str, $encoding);
}