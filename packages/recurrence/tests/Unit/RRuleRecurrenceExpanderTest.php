<?php

declare(strict_types=1);

use CoreX\Recurrence\Data\DateStart;
use CoreX\Recurrence\Data\ExpansionCursor;
use CoreX\Recurrence\Data\ExpansionLimits;
use CoreX\Recurrence\Data\RecurrenceOverride;
use CoreX\Recurrence\Data\RecurrenceSet;
use CoreX\Recurrence\Data\RecurrenceWindow;
use CoreX\Recurrence\Exceptions\StaleCursorException;
use CoreX\Recurrence\Internal\CanonicalHasher;
use CoreX\Recurrence\Internal\RuntimeProfile;
use CoreX\Recurrence\Laravel\RecurrenceFactory;

function recurrenceSet(
    string $seriesId,
    DateStart $start,
    ?string $rrule,
    array $rdates = [],
    array $exdates = [],
    array $overrides = [],
    string $revision = 'rev-1',
): RecurrenceSet {
    return RecurrenceSet::create(
        seriesId: $seriesId,
        immutableRevision: $revision,
        start: $start,
        rrule: $rrule,
        rdates: $rdates,
        exdates: $exdates,
        overrides: $overrides,
        tzdbVersion: RuntimeProfile::tzdbVersion(),
    );
}

it('REC-01 expands monthly January 31 COUNT 4 without invalid date rollover', function (): void {
    $set = recurrenceSet(
        seriesId: 'maintenance',
        start: new DateStart(valueType: 'UTC', nominalStart: '2026-01-31T09:00:00Z', tzid: null),
        rrule: 'FREQ=MONTHLY;COUNT=4',
    );

    $page = RecurrenceFactory::create()->expand(
        set: $set,
        window: new RecurrenceWindow(valueType: 'UTC', start: '2026-01-01T00:00:00Z', end: '2026-08-01T00:00:00Z'),
        limits: new ExpansionLimits,
    );

    $dates = array_map(static fn ($occurrence): string => substr($occurrence->effectiveDateOrInstant, 0, 10), $page->occurrences);

    expect($dates)->toBe(['2026-01-31', '2026-03-31', '2026-05-31', '2026-07-31'])
        ->and($page->status)->toBe('complete');
});

it('REC-02 keeps civil weekly time across America/New_York DST transitions', function (): void {
    $set = recurrenceSet(
        seriesId: 'timetable',
        start: new DateStart(valueType: 'TZID', nominalStart: '2026-03-01T09:00:00', tzid: 'America/New_York'),
        rrule: 'FREQ=WEEKLY;COUNT=3',
    );

    $page = RecurrenceFactory::create()->expand(
        set: $set,
        window: new RecurrenceWindow(valueType: 'UTC', start: '2026-03-01T00:00:00Z', end: '2026-04-01T00:00:00Z'),
        limits: new ExpansionLimits,
    );

    $wallTimes = array_map(static function ($occurrence): string {
        $instant = new DateTimeImmutable($occurrence->effectiveDateOrInstant);

        return $instant->format('H:i P');
    }, $page->occurrences);

    expect($wallTimes)->toBe(['09:00 -05:00', '09:00 -04:00', '09:00 -04:00'])
        ->and($page->status)->toBe('complete');
});

it('REC-03 omits spring gap occurrences that do not match nominal wall time', function (): void {
    $set = recurrenceSet(
        seriesId: 'gap-series',
        start: new DateStart(valueType: 'TZID', nominalStart: '2026-03-07T02:30:00', tzid: 'America/New_York'),
        rrule: 'FREQ=DAILY;COUNT=3',
    );

    $page = RecurrenceFactory::create()->expand(
        set: $set,
        window: new RecurrenceWindow(valueType: 'UTC', start: '2026-03-07T00:00:00Z', end: '2026-03-11T00:00:00Z'),
        limits: new ExpansionLimits,
    );

    expect($page->occurrences)->toHaveCount(2)
        ->and(array_map(static fn ($o) => $o->nominal, $page->occurrences))->toBe(['2026-03-07T02:30:00', '2026-03-09T02:30:00']);
});

it('REC-04 resolves autumn fold to a single first occurrence', function (): void {
    $set = recurrenceSet(
        seriesId: 'fold-series',
        start: new DateStart(valueType: 'TZID', nominalStart: '2026-11-01T01:30:00', tzid: 'America/New_York'),
        rrule: 'FREQ=WEEKLY;COUNT=2',
    );

    $page = RecurrenceFactory::create()->expand(
        set: $set,
        window: new RecurrenceWindow(valueType: 'UTC', start: '2026-10-25T00:00:00Z', end: '2026-11-15T00:00:00Z'),
        limits: new ExpansionLimits,
    );

    $nominals = array_map(static fn ($o) => $o->nominal, $page->occurrences);

    expect($nominals)->toBe(['2026-11-01T01:30:00', '2026-11-08T01:30:00'])
        ->and($page->occurrences)->toHaveCount(2);
});

it('REC-05 applies EXDATE without replenishing COUNT and unions RDATE once', function (): void {
    $set = recurrenceSet(
        seriesId: 'exceptions',
        start: new DateStart(valueType: 'UTC', nominalStart: '2026-01-01T09:00:00Z', tzid: null),
        rrule: 'FREQ=DAILY;COUNT=4',
        exdates: ['2026-01-02T09:00:00Z'],
        rdates: ['2026-01-02T09:00:00Z'],
    );

    $page = RecurrenceFactory::create()->expand(
        set: $set,
        window: new RecurrenceWindow(valueType: 'UTC', start: '2026-01-01T00:00:00Z', end: '2026-01-10T00:00:00Z'),
        limits: new ExpansionLimits,
    );

    $dates = array_map(static fn ($o) => substr($o->effectiveDateOrInstant, 0, 10), $page->occurrences);

    expect($dates)->toBe(['2026-01-01', '2026-01-03', '2026-01-04'])
        ->and($page->status)->toBe('complete');
});

it('REC-06 rejects COUNT with UNTIL and unsupported profile features', function (): void {
    $expander = RecurrenceFactory::create();
    $window = new RecurrenceWindow(valueType: 'UTC', start: '2026-01-01T00:00:00Z', end: '2026-02-01T00:00:00Z');
    $start = new DateStart(valueType: 'UTC', nominalStart: '2026-01-01T09:00:00Z', tzid: null);

    $invalid = recurrenceSet(seriesId: 'series', start: $start, rrule: 'FREQ=DAILY;COUNT=2;UNTIL=20260102T090000Z');
    $unsupported = recurrenceSet(seriesId: 'series', start: $start, rrule: 'FREQ=DAILY;BYSECOND=60');

    expect($expander->expand($invalid, $window, new ExpansionLimits)->status)->toBe('invalid')
        ->and($expander->expand($unsupported, $window, new ExpansionLimits)->status)->toBe('unsupported');
});

it('REC-07 expands DATE windows without midnight conversion', function (): void {
    $set = recurrenceSet(
        seriesId: 'all-day',
        start: new DateStart(valueType: 'DATE', nominalStart: '2026-01-10', tzid: null),
        rrule: 'FREQ=DAILY;COUNT=3',
    );

    $page = RecurrenceFactory::create()->expand(
        set: $set,
        window: new RecurrenceWindow(valueType: 'DATE', start: '2026-01-11', end: '2026-01-13'),
        limits: new ExpansionLimits,
    );

    expect(array_map(static fn ($o) => $o->effectiveDateOrInstant, $page->occurrences))
        ->toBe(['2026-01-11', '2026-01-12'])
        ->and($page->status)->toBe('complete');
});

it('REC-08 preserves occurrence keys across pages and applies overrides', function (): void {
    $set = recurrenceSet(
        seriesId: 'cursor-series',
        start: new DateStart(valueType: 'UTC', nominalStart: '2026-01-01T09:00:00Z', tzid: null),
        rrule: 'FREQ=DAILY;COUNT=4',
        overrides: [
            new RecurrenceOverride(originalRecurrenceId: '2026-01-02T09:00:00Z', effectiveStart: '2026-01-02T11:00:00Z', cancelled: false),
            new RecurrenceOverride(originalRecurrenceId: '2026-01-03T09:00:00Z', effectiveStart: null, cancelled: true),
        ],
    );

    $window = new RecurrenceWindow(valueType: 'UTC', start: '2026-01-01T00:00:00Z', end: '2026-01-10T00:00:00Z');
    $expander = RecurrenceFactory::create();

    $first = $expander->expand(set: $set, window: $window, limits: new ExpansionLimits(emitted: 2));
    $second = $expander->expand(set: $set, window: $window, limits: new ExpansionLimits(emitted: 10), after: $first->nextCursor);

    $keys = array_map(static fn ($o) => $o->key, array_merge($first->occurrences, $second->occurrences));
    $expectedKey = CanonicalHasher::occurrenceKey(
        seriesId: $set->seriesId,
        immutableRevision: $set->immutableRevision,
        valueType: 'UTC',
        nominal: '2026-01-02T09:00:00Z',
        tzid: null,
    );

    expect($first->occurrences[1]->key)->toBe($expectedKey)
        ->and($first->occurrences[1]->effectiveDateOrInstant)->toBe('2026-01-02T11:00:00Z')
        ->and(count($keys))->toBe(count(array_unique($keys)))
        ->and(array_any(array_merge($first->occurrences, $second->occurrences), static fn ($o): bool => $o->originalRecurrenceId === '2026-01-03T09:00:00Z'))->toBeFalse();
});

it('REC-09 stops within candidate and wall limits before first emission when needed', function (): void {
    $set = recurrenceSet(
        seriesId: 'dense',
        start: new DateStart(valueType: 'UTC', nominalStart: '1990-01-01T00:00:00Z', tzid: null),
        rrule: 'FREQ=SECONDLY;COUNT=1000000',
    );

    $page = RecurrenceFactory::create()->expand(
        set: $set,
        window: new RecurrenceWindow(valueType: 'UTC', start: '2026-01-01T00:00:00Z', end: '2026-02-01T00:00:00Z'),
        limits: new ExpansionLimits(emitted: 10, candidateSteps: 50, wallSeconds: 30),
    );

    expect($page->status)->toBe('partial')
        ->and($page->occurrences)->toBeEmpty()
        ->and($page->completeThrough)->toBeNull()
        ->and($page->scanned)->toBeGreaterThanOrEqual(50);
});

it('REC-10 rejects cursor hash mismatches', function (): void {
    $set = recurrenceSet(
        seriesId: 'hash-series',
        start: new DateStart(valueType: 'UTC', nominalStart: '2026-01-01T09:00:00Z', tzid: null),
        rrule: 'FREQ=DAILY;COUNT=2',
    );

    $window = new RecurrenceWindow(valueType: 'UTC', start: '2026-01-01T00:00:00Z', end: '2026-01-10T00:00:00Z');
    $cursor = new ExpansionCursor(
        setHash: str_repeat('a', 64),
        windowHash: CanonicalHasher::windowHash(window: $window),
        tzdbVersion: RuntimeProfile::tzdbVersion(),
        libraryVersion: RuntimeProfile::libraryVersion(),
        resumePosition: ['scanned' => 0, 'emitted' => 0],
    );

    expect(fn () => RecurrenceFactory::create()->expand(set: $set, window: $window, limits: new ExpansionLimits, after: $cursor))
        ->toThrow(StaleCursorException::class);
});

it('REC-11 refuses pinned tzdb versions that differ from runtime', function (): void {
    $set = RecurrenceSet::create(
        seriesId: 'tzdb-series',
        immutableRevision: 'rev-1',
        start: new DateStart(valueType: 'UTC', nominalStart: '2026-01-01T09:00:00Z', tzid: null),
        rrule: 'FREQ=DAILY;COUNT=1',
        tzdbVersion: '1970.1',
    );

    $page = RecurrenceFactory::create()->expand(
        set: $set,
        window: new RecurrenceWindow(valueType: 'UTC', start: '2026-01-01T00:00:00Z', end: '2026-01-10T00:00:00Z'),
        limits: new ExpansionLimits,
    );

    expect($page->status)->toBe('unsupported')
        ->and($page->occurrences)->toBeEmpty();
});
