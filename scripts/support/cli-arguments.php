<?php

declare(strict_types=1);

namespace CoreX\Scripts;

use RuntimeException;

/** @return non-empty-list<string> */
function cliArguments(): array
{
    $input = $_SERVER['argv'] ?? null;

    if (! is_array($input) || ! array_is_list($input) || $input === []) {
        throw new RuntimeException('CLI argument vector is unavailable.');
    }

    $arguments = [];
    foreach ($input as $argument) {
        if (! is_string($argument)) {
            throw new RuntimeException('CLI argument vector contains a non-string value.');
        }

        $arguments[] = $argument;
    }

    return $arguments;
}
