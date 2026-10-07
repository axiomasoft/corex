<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Internal;

use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\RecurrenceOverride;
use CoreX\Recurrence\Data\RecurrenceWindow;

final class CanonicalHasher
{
    /**
     * @param  list<string>  $rdates
     * @param  list<string>  $exdates
     * @param  list<RecurrenceOverride>  $overrides
     */
    public static function definitionHash(
        string $seriesId,
        string $immutableRevision,
        DateStart $start,
        ?string $rrule,
        array $rdates,
        array $exdates,
        array $overrides,
        string $tzdbVersion,
    ): string {
        $overridePayload = array_map(
            static fn (RecurrenceOverride $override): array => [
                'originalRecurrenceId' => $override->originalRecurrenceId,
                'effectiveStart' => $override->effectiveStart,
                'cancelled' => $override->cancelled,
            ],
            $overrides,
        );

        $canonical = json_encode([
            'schemaVersion' => 'recurrence/1',
            'seriesId' => $seriesId,
            'immutableRevision' => $immutableRevision,
            'start' => [
                'valueType' => $start->valueType,
                'nominalStart' => $start->nominalStart,
                'tzid' => $start->tzid,
            ],
            'rrule' => $rrule,
            'rdates' => $rdates,
            'exdates' => $exdates,
            'overrides' => $overridePayload,
            'tzdbVersion' => $tzdbVersion,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', $canonical);
    }

    public static function windowHash(RecurrenceWindow $window): string
    {
        return hash('sha256', json_encode([
            'valueType' => $window->valueType,
            'start' => $window->start,
            'end' => $window->end,
        ], JSON_THROW_ON_ERROR));
    }

    public static function occurrenceKey(
        string $seriesId,
        string $immutableRevision,
        string $valueType,
        string $nominal,
        ?string $tzid,
    ): string {
        $parts = [$seriesId, $immutableRevision, $valueType, $nominal, $tzid ?? ''];
        $canonical = '';

        foreach ($parts as $part) {
            $canonical .= pack('N', strlen($part)).$part;
        }

        return hash('sha256', $canonical);
    }
}
