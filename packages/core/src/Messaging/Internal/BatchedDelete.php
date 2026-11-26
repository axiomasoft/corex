<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Packman\Attributes\Internal;

/**
 * Deletes the rows a query selects in primary-key batches.
 *
 * A single unbounded DELETE over a retention backlog holds row locks and grows
 * the WAL for the whole backlog in one transaction; batches keep each statement
 * short so the relay and writers on the same table are not stalled.
 */
#[Internal]
final class BatchedDelete
{
    public const int DEFAULT_BATCH = 1000;

    public static function run(Builder $query, string $key, int $batch): int
    {
        if ($batch < 1) {
            throw new InvalidArgumentException('Batch size must be a positive integer.');
        }

        $deleted = 0;

        do {
            $ids = (clone $query)->orderBy($key)->limit($batch)->pluck($key)->all();

            if ($ids === []) {
                break;
            }

            $deleted += (clone $query)->whereIn($key, $ids)->delete();
        } while (count($ids) === $batch);

        return $deleted;
    }

    public static function batchOption(mixed $value): int
    {
        if ($value === null) {
            return self::DEFAULT_BATCH;
        }

        if (! is_string($value) || preg_match('/^[1-9][0-9]{0,6}$/D', $value) !== 1) {
            throw new InvalidArgumentException('--batch must be a positive integer.');
        }

        return (int) $value;
    }
}
