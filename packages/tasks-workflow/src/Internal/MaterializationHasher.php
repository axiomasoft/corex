<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Internal;

use CoreX\Data\Data\DataScope;
use CoreX\TasksWorkflow\Data\MaterializationWindow;
use CoreX\TasksWorkflow\Data\SeriesReference;
use CoreX\TasksWorkflow\Exceptions\StaleMaterialization;
use CoreX\TasksWorkflow\Exceptions\UnsupportedMaterialization;

final class MaterializationHasher
{
    public const string HANDLER_VERSION = 'task-materialization/1';

    public const string HANDLER_KIND = 'task-recurrence.materialize';

    public static function payload(
        DataScope $scope,
        SeriesReference $series,
        MaterializationWindow $window,
    ): string {
        return hash('sha256', implode("\0", [
            self::HANDLER_VERSION,
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

    /**
     * @return array{
     *     seriesId: string,
     *     immutableRevision: string,
     *     definitionHash: string,
     *     start: string,
     *     end: string,
     *     aheadDays: int,
     *     pageLimit: int,
     *     maxSeconds: int
     * }
     */
    public static function wakeupPayload(SeriesReference $series, MaterializationWindow $window): array
    {
        return [
            'seriesId' => $series->id,
            'immutableRevision' => $series->immutableRevision,
            'definitionHash' => $series->definitionHash,
            'start' => $window->start,
            'end' => $window->end,
            'aheadDays' => $window->aheadDays,
            'pageLimit' => $window->pageLimit,
            'maxSeconds' => $window->maxSeconds,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array{series: SeriesReference, window: MaterializationWindow}
     */
    public static function decodeWakeupPayload(array $payload): array
    {
        $seriesId = self::requiredString(payload: $payload, key: 'seriesId');
        $revision = self::requiredString(payload: $payload, key: 'immutableRevision');
        $definitionHash = self::requiredString(payload: $payload, key: 'definitionHash');
        $start = self::requiredString(payload: $payload, key: 'start');
        $end = self::requiredString(payload: $payload, key: 'end');

        if ($seriesId === '' || $revision === '' || $definitionHash === '' || $start === '' || $end === '') {
            throw $seriesId === ''
                ? UnsupportedMaterialization::connection('Materialization wakeup payload is incomplete.')
                : StaleMaterialization::definition($seriesId);
        }

        return [
            'series' => new SeriesReference(
                id: $seriesId,
                immutableRevision: $revision,
                definitionHash: $definitionHash,
            ),
            'window' => new MaterializationWindow(
                start: $start,
                end: $end,
                aheadDays: self::optionalInt(payload: $payload, key: 'aheadDays', default: 30),
                pageLimit: self::optionalInt(payload: $payload, key: 'pageLimit', default: 100),
                maxSeconds: self::optionalInt(payload: $payload, key: 'maxSeconds', default: 5),
            ),
        ];
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

    /** @param  array<array-key, mixed>  $payload */
    private static function requiredString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        if (! is_string($value)) {
            return '';
        }

        return $value;
    }

    /** @param  array<array-key, mixed>  $payload */
    private static function optionalInt(array $payload, string $key, int $default): int
    {
        $value = $payload[$key] ?? $default;

        return is_int($value) ? $value : $default;
    }
}
