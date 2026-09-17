<?php

declare(strict_types=1);

namespace CoreX\IntlSearch\Internal;

final class TranslationRevision
{
    /**
     * @param  array<string, string|null>  $map
     */
    public function compute(array $map, string $schemaHash): string
    {
        ksort($map);

        return hash('sha256', $schemaHash.'|'.json_encode($map, JSON_THROW_ON_ERROR));
    }

    public function nextRevision(string $currentRevision, int $sequence): int
    {
        return max(1, $sequence, hexdec(substr($currentRevision, 0, 8)) % 1000000);
    }
}
