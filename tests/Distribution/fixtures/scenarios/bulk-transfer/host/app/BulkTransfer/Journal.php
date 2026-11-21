<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use RuntimeException;

final class Journal
{
    public static function record(string $event, array $data): void
    {
        $line = json_encode(['event' => $event, 'pid' => getmypid(), ...$data], JSON_THROW_ON_ERROR)."\n";

        if (file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX) !== strlen($line)) {
            throw new RuntimeException('Cannot persist the provider journal entry.');
        }
    }

    public static function events(string $event): array
    {
        if (! is_file(self::path())) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), file(self::path(), FILE_IGNORE_NEW_LINES)),
            static fn (array $line): bool => $line['event'] === $event,
        ));
    }

    private static function path(): string
    {
        $root = getenv('COREX_SCENARIO_BARRIERS');

        if (! is_string($root) || ! is_dir($root)) {
            throw new RuntimeException('The runner did not allocate a provider journal directory.');
        }

        return $root.'/bulk-provider.ndjson';
    }
}
