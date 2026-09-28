<?php

declare(strict_types=1);

namespace CoreX\Tests\Golden\Support;

final class Normalizer
{
    public static function normalize(mixed $value, array $path = []): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        // Internal status and interface ordering derive from projected class names.
        if (count($path) === 1 && isset($value['kind'], $value['methods'], $value['interfaces'])) {
            $value['internal'] = str_contains((string) $path[0], '\\Internal\\');
            sort($value['interfaces']);
        }

        // These hashes depend on renamed module/registry identities; their semantic
        // inputs remain in the same record. Never exclude a same-named schema/member.
        if (count($path) === 2 && $path[0] === 'operations' && isset($value['id'], $value['request_schema'], $value['response_schema'])) {
            unset($value['contract_hash']);
        }
        foreach ($value as $key => $item) {
            $value[$key] = self::normalize($item, [...$path, $key]);
        }
        // PHP array insertion order in constants/defaults is observable. Preserve
        // it at every nested depth; sorting the surrounding record is still safe.
        $orderedValue = (count($path) >= 4 && $path[1] === 'constants' && $path[3] === 'value')
            || (count($path) >= 6 && $path[1] === 'methods' && $path[3] === 'params' && $path[5] === 'default');

        if (! $orderedValue && ! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
