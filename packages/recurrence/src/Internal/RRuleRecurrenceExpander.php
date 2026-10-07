<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Internal;

use CoreX\Recurrence\Contracts\RecurrenceExpander;
use CoreX\Recurrence\Data\ExpansionCursor;
use CoreX\Recurrence\Data\ExpansionLimits;
use CoreX\Recurrence\Data\Occurrence;
use CoreX\Recurrence\Data\OccurrencePage;
use CoreX\Recurrence\Data\RecurrenceOverride;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Recurrence\Data\RecurrenceWindow;
use CoreX\Recurrence\Exceptions\StaleCursorException;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RRule\RSet;

final class RRuleRecurrenceExpander implements RecurrenceExpander
{
    public function expand(RecurrenceSet $set, RecurrenceWindow $window, ExpansionLimits $limits, ?ExpansionCursor $after = null): OccurrencePage
    {
        $runtimeTzdb = RuntimeProfile::tzdbVersion();
        $libraryVersion = RuntimeProfile::libraryVersion();
        $windowHash = CanonicalHasher::windowHash(window: $window);

        if ($set->tzdbVersion !== $runtimeTzdb) {
            return $this->emptyPage(
                set: $set,
                status: 'unsupported',
                scanned: 0,
                emitted: 0,
            );
        }

        if ($set->start->valueType === 'DATE' && $window->valueType !== 'DATE') {
            return $this->emptyPage(set: $set, status: 'invalid', scanned: 0, emitted: 0);
        }

        if ($set->start->valueType !== 'DATE' && $window->valueType !== 'UTC') {
            return $this->emptyPage(set: $set, status: 'invalid', scanned: 0, emitted: 0);
        }

        if ($after !== null) {
            $this->assertCursor(
                cursor: $after,
                set: $set,
                windowHash: $windowHash,
                runtimeTzdb: $runtimeTzdb,
                libraryVersion: $libraryVersion,
            );
        }

        $validation = $this->validateDefinition(set: $set, limits: $limits);

        if ($validation !== null) {
            return $this->emptyPage(set: $set, status: $validation, scanned: 0, emitted: 0);
        }

        $overrideMap = $this->overrideMap(overrides: $set->overrides);
        $nominalPattern = $this->nominalPattern(set: $set);
        $resumeOffset = $after === null ? 0 : ($after->resumePosition['scanned'] ?? 0);
        $emittedSoFar = $after === null ? 0 : ($after->resumePosition['emitted'] ?? 0);
        $seenKeys = [];
        $occurrences = [];
        $scanned = 0;
        $emitted = 0;
        $completeThrough = null;
        $startedAt = microtime(true);
        $memoryLimit = $limits->memoryMiB * 1024 * 1024;
        $status = 'complete';

        $source = $this->buildRSet(set: $set);
        $windowBounds = $this->windowBounds(set: $set, window: $window, limits: $limits);

        foreach ($source as $candidate) {
            if ($scanned < $resumeOffset) {
                $scanned++;

                continue;
            }

            $scanned++;

            if ($scanned > $limits->candidateSteps) {
                $status = 'partial';

                break;
            }

            if ((microtime(true) - $startedAt) > $limits->wallSeconds) {
                $status = 'partial';

                break;
            }

            if (memory_get_usage(true) > $memoryLimit) {
                $status = 'partial';

                break;
            }

            $candidateInstant = DateTimeImmutable::createFromMutable($candidate);

            if (! $this->withinWindow(candidate: $candidateInstant, set: $set, window: $window, bounds: $windowBounds)) {
                if ($this->pastWindow(candidate: $candidateInstant, set: $set, window: $window, bounds: $windowBounds)) {
                    break;
                }

                continue;
            }

            if ($set->start->valueType === 'TZID' && ! $this->matchesNominalWallTime(candidate: $candidateInstant, pattern: $nominalPattern)) {
                continue;
            }

            $originalRecurrenceId = $this->originalRecurrenceId(set: $set, candidate: $candidateInstant);
            $nominal = $this->nominalValue(set: $set, originalRecurrenceId: $originalRecurrenceId);
            $key = CanonicalHasher::occurrenceKey(
                seriesId: $set->seriesId,
                immutableRevision: $set->immutableRevision,
                valueType: $set->start->valueType,
                nominal: $nominal,
                tzid: $set->start->tzid,
            );

            if (isset($seenKeys[$key])) {
                continue;
            }

            $seenKeys[$key] = true;

            if (isset($overrideMap[$originalRecurrenceId]) && $overrideMap[$originalRecurrenceId]->cancelled) {
                continue;
            }

            $effective = $this->effectiveValue(
                set: $set,
                candidate: $candidateInstant,
                override: $overrideMap[$originalRecurrenceId] ?? null,
            );
            $offset = $this->offsetSeconds(set: $set, effective: $effective);

            $occurrences[] = new Occurrence(
                key: $key,
                originalRecurrenceId: $originalRecurrenceId,
                valueType: $set->start->valueType,
                tzid: $set->start->tzid,
                nominal: $nominal,
                effectiveDateOrInstant: $effective,
                offset: $offset,
                tzdbVersion: $runtimeTzdb,
                definitionHash: $set->definitionHash,
            );

            $emitted++;
            $completeThrough = $effective;

            if (($emittedSoFar + $emitted) >= $limits->emitted) {
                $status = $this->hasMoreCandidates(source: $source, scanned: $scanned) ? 'partial' : 'complete';

                break;
            }
        }

        $totalEmitted = $emittedSoFar + $emitted;
        $nextCursor = $status === 'partial'
            ? new ExpansionCursor(
                setHash: $set->definitionHash,
                windowHash: $windowHash,
                tzdbVersion: $runtimeTzdb,
                libraryVersion: $libraryVersion,
                resumePosition: [
                    'scanned' => $scanned,
                    'emitted' => $totalEmitted,
                ],
            )
            : null;

        if ($status === 'partial' && $emitted === 0) {
            $completeThrough = null;
        }

        return new OccurrencePage(
            occurrences: $occurrences,
            status: $status,
            nextCursor: $nextCursor,
            completeThrough: $completeThrough,
            scanned: $scanned,
            emitted: $totalEmitted,
            definitionHash: $set->definitionHash,
        );
    }

    private function emptyPage(RecurrenceSet $set, string $status, int $scanned, int $emitted): OccurrencePage
    {
        return new OccurrencePage(
            occurrences: [],
            status: $status,
            nextCursor: null,
            completeThrough: null,
            scanned: $scanned,
            emitted: $emitted,
            definitionHash: $set->definitionHash,
        );
    }

    private function assertCursor(
        ExpansionCursor $cursor,
        RecurrenceSet $set,
        string $windowHash,
        string $runtimeTzdb,
        string $libraryVersion,
    ): void {
        if (
            $cursor->setHash !== $set->definitionHash
            || $cursor->windowHash !== $windowHash
            || $cursor->tzdbVersion !== $runtimeTzdb
            || $cursor->libraryVersion !== $libraryVersion
        ) {
            throw new StaleCursorException('The cursor does not belong to this recurrence definition.');
        }
    }

    private function validateDefinition(RecurrenceSet $set, ExpansionLimits $limits): ?string
    {
        if ($set->rrule !== null && strlen($set->rrule) > $limits->ruleBytes) {
            return 'invalid';
        }

        if (count($set->rdates) + count($set->exdates) > $limits->exceptionCount) {
            return 'invalid';
        }

        if ($set->rrule !== null) {
            if (str_contains($set->rrule, 'COUNT=') && str_contains($set->rrule, 'UNTIL=')) {
                return 'invalid';
            }

            if (preg_match('/(?:^|;)BYSECOND=60(?:;|$)/', $set->rrule) === 1) {
                return 'unsupported';
            }

            if (preg_match('/(?:VTIMEZONE|RANGE=THISANDFUTURE|EXRULE)/', $set->rrule) === 1) {
                return 'unsupported';
            }
        }

        if ($set->start->valueType === 'TZID' && str_contains($set->start->nominalStart, 'Z')) {
            return 'invalid';
        }

        return null;
    }

    /**
     * @param  list<RecurrenceOverride>  $overrides
     * @return array<string, RecurrenceOverride>
     */
    private function overrideMap(array $overrides): array
    {
        $map = [];

        foreach ($overrides as $override) {
            $map[$override->originalRecurrenceId] = $override;
        }

        return $map;
    }

    /** @return RSet<int, DateTime> */
    private function buildRSet(RecurrenceSet $set): RSet
    {
        $lines = [$this->dtStartLine(set: $set)];

        if ($set->rrule !== null) {
            $lines[] = 'RRULE:'.$set->rrule;
        }

        foreach ($set->rdates as $rdate) {
            $lines[] = $this->exceptionLine(property: 'RDATE', value: $rdate, set: $set);
        }

        foreach ($set->exdates as $exdate) {
            $lines[] = $this->exceptionLine(property: 'EXDATE', value: $exdate, set: $set);
        }

        return new RSet(implode("\n", $lines));
    }

    private function exceptionLine(string $property, string $value, RecurrenceSet $set): string
    {
        $compact = $this->compactDate(value: $value, valueType: $set->start->valueType);

        return match ($set->start->valueType) {
            'DATE' => $property.';VALUE=DATE:'.$compact,
            'TZID' => $property.';TZID='.$set->start->tzid.':'.$compact,
            'UTC' => $property.':'.$compact.'Z',
            default => throw new InvalidArgumentException('Unsupported recurrence value type.'),
        };
    }

    private function dtStartLine(RecurrenceSet $set): string
    {
        $compact = $this->compactDate(value: $set->start->nominalStart, valueType: $set->start->valueType);

        return match ($set->start->valueType) {
            'DATE' => 'DTSTART;VALUE=DATE:'.$compact,
            'TZID' => 'DTSTART;TZID='.$set->start->tzid.':'.$compact,
            'UTC' => 'DTSTART:'.$compact.'Z',
            default => throw new InvalidArgumentException('Unsupported recurrence value type.'),
        };
    }

    private function compactDate(string $value, string $valueType): string
    {
        if ($valueType === 'DATE') {
            return str_replace('-', '', $value);
        }

        if ($valueType === 'UTC') {
            return new DateTimeImmutable($value)
                ->setTimezone(new DateTimeZone('UTC'))
                ->format('Ymd\\THis');
        }

        return str_replace(['-', ':'], '', substr($value, 0, 19));
    }

    /**
     * @return array{begin: DateTime, end: DateTime}
     */
    private function windowBounds(RecurrenceSet $set, RecurrenceWindow $window, ExpansionLimits $limits): array
    {
        if ($set->start->valueType === 'DATE') {
            $begin = DateTime::createFromFormat('Y-m-d', $window->start);
            $begin = $begin === false ? new DateTime($window->start) : $begin;
            $end = DateTime::createFromFormat('Y-m-d', $window->end);
            $end = $end === false ? new DateTime($window->end) : $end;
            $end->modify('-1 day');

            return ['begin' => $begin, 'end' => $end];
        }

        $begin = new DateTime($window->start);
        $end = new DateTime($window->end);
        $end->modify('-1 second');
        $maxEnd = (clone $begin)->modify('+'.$limits->rangeDays.' days');

        if ($end > $maxEnd) {
            $end = $maxEnd;
        }

        return ['begin' => $begin, 'end' => $end];
    }

    /**
     * @param  array{begin: DateTime, end: DateTime}  $bounds
     */
    private function withinWindow(DateTimeImmutable $candidate, RecurrenceSet $set, RecurrenceWindow $window, array $bounds): bool
    {
        if ($set->start->valueType === 'DATE') {
            $date = $candidate->format('Y-m-d');

            return $date >= $window->start && $date < $window->end;
        }

        $instant = $candidate->setTimezone(new DateTimeZone('UTC'));

        return $instant >= new DateTimeImmutable($window->start) && $instant < new DateTimeImmutable($window->end);
    }

    /**
     * @param  array{begin: DateTime, end: DateTime}  $bounds
     */
    private function pastWindow(DateTimeImmutable $candidate, RecurrenceSet $set, RecurrenceWindow $window, array $bounds): bool
    {
        if ($set->start->valueType === 'DATE') {
            return $candidate->format('Y-m-d') >= $window->end;
        }

        return $candidate->setTimezone(new DateTimeZone('UTC')) >= new DateTimeImmutable($window->end);
    }

    /** @return array{hour: int, minute: int, second: int} */
    private function nominalPattern(RecurrenceSet $set): array
    {
        if ($set->start->valueType === 'DATE') {
            return ['hour' => 0, 'minute' => 0, 'second' => 0];
        }

        $instant = new DateTimeImmutable($set->start->nominalStart, $set->start->valueType === 'TZID' ? new DateTimeZone($set->start->tzid ?? 'UTC') : new DateTimeZone('UTC'));

        return [
            'hour' => (int) $instant->format('H'),
            'minute' => (int) $instant->format('i'),
            'second' => (int) $instant->format('s'),
        ];
    }

    /** @param array{hour: int, minute: int, second: int} $pattern */
    private function matchesNominalWallTime(DateTimeImmutable $candidate, array $pattern): bool
    {
        return (int) $candidate->format('H') === $pattern['hour']
            && (int) $candidate->format('i') === $pattern['minute']
            && (int) $candidate->format('s') === $pattern['second'];
    }

    private function originalRecurrenceId(RecurrenceSet $set, DateTimeImmutable $candidate): string
    {
        if ($set->start->valueType === 'DATE') {
            return $candidate->format('Y-m-d');
        }

        if ($set->start->valueType === 'TZID') {
            return $candidate->format('Y-m-d\\TH:i:s');
        }

        return $candidate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
    }

    private function nominalValue(RecurrenceSet $set, string $originalRecurrenceId): string
    {
        return $originalRecurrenceId;
    }

    private function effectiveValue(RecurrenceSet $set, DateTimeImmutable $candidate, ?RecurrenceOverride $override): string
    {
        if ($override !== null && $override->effectiveStart !== null) {
            return $override->effectiveStart;
        }

        if ($set->start->valueType === 'DATE') {
            return $candidate->format('Y-m-d');
        }

        if ($set->start->valueType === 'TZID') {
            return $candidate->format('Y-m-d\\TH:i:sP');
        }

        return $candidate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
    }

    private function offsetSeconds(RecurrenceSet $set, string $effective): ?int
    {
        if ($set->start->valueType !== 'TZID') {
            return null;
        }

        $instant = new DateTimeImmutable($effective);

        return $instant->getOffset();
    }

    /** @param RSet<int, DateTime> $source */
    private function hasMoreCandidates(RSet $source, int $scanned): bool
    {
        $index = 0;

        foreach ($source as $_) {
            $index++;

            if ($index > $scanned) {
                return true;
            }
        }

        return false;
    }
}
