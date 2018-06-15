<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Parser;
use function Stringy\create as s;

final class AdblockParser
{

    const EXACT_MATCH = 'exact_match';
    const DOMAIN_NAME = 'domain_name';
    const ADDRESS_PARTS = 'address_parts';

    public function __invoke(string $line)
    {
        $rule = s($line);
        $output = [
            'pattern' => $line,
            'options' => null,
            'exception' => false,
            'type' => self::ADDRESS_PARTS,
            'domain' => null,
            'request_uri' => null,
        ];

        // Set options apart
        if ($rule->contains('$')) {
            $output['options'] = (string) s(strstr($line, '$'))->removeLeft('$');
            $rule = $rule->removeRight($output['options'])->removeRight('$');
        }

        if ($rule->startsWith('@@')) {
            $output['exception'] = true;
            $rule = $rule->removeLeft('@@');
        }

        if ($rule->startsWith('|') && $rule->endsWith('|')) {
            $output['type'] = self::EXACT_MATCH;
            $rule = $rule->removeLeft('|')->removeRight('|');
        }

        if ($rule->startsWith('||') && $rule->endsWith('^')) {
            $output['type'] = self::DOMAIN_NAME;
            $rule = $rule->removeLeft('||')->removeRight('^');
            $output['domain'] = (string) $rule;
        }

        if ($rule->startsWith('||')) {
            $rule = $rule->removeLeft('||');
            $output['domain'] = strstr((string) $rule, '/', true);
            $output['request_uri'] = strstr((string) $rule, '/');
        }

        $output['pattern'] = (string) $rule;

        return $output;
    }

}
