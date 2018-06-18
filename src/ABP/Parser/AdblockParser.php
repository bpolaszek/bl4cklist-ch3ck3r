<?php

namespace BenTools\Bl4cklistCh3ck3r\ABP\Parser;
use function Stringy\create as s;

final class AdblockParser
{

    const EXACT_MATCH = 'exact_match';
    const DOMAIN_NAME = 'domain_name';
    const DOMAIN_URI = 'domain_uri';
    const ADDRESS_PARTS = 'address_parts';
    const APPLY_FILTER = 'include';
    const IGNORE_FILTER = 'ignore';
    const BOOLEAN_CONTEXTS = ['script', 'image', 'stylesheet', 'object', 'object-subrequest', 'subdocument', 'third-party', 'popup', 'xmlhttprequest'];

    /**
     * @param string $line
     * @return array
     * @throws \InvalidArgumentException
     */
    public function parse(string $line)
    {
        $rule = s($line);
        $output = [
            'source' => $line,
            'pattern' => $line,
            'options' => [],
            'exception' => false,
            'type' => self::ADDRESS_PARTS,
            'hostname' => null,
            'request_uri' => null,
        ];

        // Options
        if ($rule->contains('$')) {
            $options = (string) s(strstr($line, '$'))->removeLeft('$');
            $this->parseOptions($options, $output);
            $rule = $rule->removeRight($options)->removeRight('$');
        }

        // Exceptions
        if ($rule->startsWith('@@')) {
            $output['exception'] = true;
            $rule = $rule->removeLeft('@@');
        }

        // Exact URL match
        if ($rule->startsWith('|') && $rule->endsWith('|')) {
            $output['type'] = self::EXACT_MATCH;
            $rule = $rule->removeLeft('|')->removeRight('|');
        }

        // Domain match
        if ($rule->startsWith('||') && $rule->endsWith('^')) {
            $output['type'] = self::DOMAIN_NAME;
            $rule = $rule->removeLeft('||')->removeRight('^');
            $output['hostname'] = (string) $rule;
        }

        // Domain + URL parts
        if ($rule->startsWith('||')) {
            $output['type'] = self::DOMAIN_URI;
            $rule = $rule->removeLeft('||');
            $output['hostname'] = strstr((string) $rule, '/', true);
            $output['request_uri'] = strstr((string) $rule, '/');
        }

        $output['pattern'] = (string) $rule;

        return $output;
    }

    /**
     * @param string $options
     * @param array  $output
     * @throws \InvalidArgumentException
     */
    private function parseOptions(string $options, array &$output)
    {
        $options = explode(',', $options);
        foreach ($options as $option) {
            $option = s($option);
            if (in_array((string) $option->removeLeft('~'), self::BOOLEAN_CONTEXTS)) {
                $output['options'][(string) $option->removeLeft('~')] = $option->startsWith('~') ? self::IGNORE_FILTER : self::APPLY_FILTER;
            } elseif ($option->startsWith('domain=')) {
                $domains = explode('|', (string) $option->removeLeft('domain='));
                foreach ($domains as $domain) {
                    $domain = s($domain);
                    $output['options']['domain'][(string) $domain->removeLeft('~')] = $domain->startsWith('~') ? self::IGNORE_FILTER : self::APPLY_FILTER;
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function __invoke(string $line)
    {
        return $this->parse($line);
    }

}
