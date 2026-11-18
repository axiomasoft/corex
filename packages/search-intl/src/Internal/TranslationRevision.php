<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Internal;

use Packman\Attributes\Internal;

#[Internal]
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
}
