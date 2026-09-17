<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Support\Data\DataScope;
use CoreX\TasksWorkflow\Data\MaterializationWindow;
use CoreX\TasksWorkflow\Data\SeriesReference;

final class MaterializationHasher
{
    public static function payload(
        DataScope $scope,
        SeriesReference $series,
        MaterializationWindow $window,
    ): string {
        return hash('sha256', implode("\0", [
            'task-materialization/1',
            $scope->restoreEpoch,
            $series->id,
            $series->immutableRevision,
            $series->definitionHash,
            $window->start,
            $window->end,
            (string) $window->aheadDays,
            (string) $window->pageLimit,
            (string) $window->maxSeconds,
        ]));
    }

    /** @param list<string> $taskRefIds */
    public static function pageDigest(array $taskRefIds): string
    {
        sort($taskRefIds);

        return hash('sha256', 'task-materialization/page/1'."\0".implode("\0", $taskRefIds));
    }

    public static function occurrenceOperationKey(string $seriesKey, string $revision, string $occurrenceKey): string
    {
        return hash('sha256', 'task-materialization/occurrence/1'."\0".$seriesKey."\0".$revision."\0".$occurrenceKey);
    }
}
