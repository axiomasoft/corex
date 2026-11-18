<?php

declare(strict_types=1);

namespace CoreX\Recurrence\Data;

use CoreX\Recurrence\Internal\CanonicalHasher;
use CoreX\Recurrence\Internal\RuntimeProfile;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class RecurrenceSet
{
    /**
     * @param  list<string>  $rdates
     * @param  list<string>  $exdates
     * @param  list<RecurrenceOverride>  $overrides
     */
    public function __construct(
        public string $schemaVersion,
        public string $seriesId,
        public string $immutableRevision,
        public DateStart $start,
        public ?string $rrule,
        public array $rdates,
        public array $exdates,
        public array $overrides,
        public string $tzdbVersion,
        public string $definitionHash,
    ) {
        if ($schemaVersion !== 'recurrence/1') {
            throw new InvalidArgumentException('The recurrence schema version is unsupported.');
        }

        if ($seriesId === '' || $immutableRevision === '') {
            throw new InvalidArgumentException('The recurrence definition is invalid.');
        }

        if ($rrule !== null && (! str_starts_with($rrule, 'FREQ=') || strlen($rrule) > 8192)) {
            throw new InvalidArgumentException('The recurrence rule is invalid.');
        }

        if ($rrule === null && $rdates === []) {
            throw new InvalidArgumentException('The recurrence definition requires a rule or RDATE values.');
        }

        if (count($rdates) + count($exdates) > 10000) {
            throw new InvalidArgumentException('The recurrence exception list exceeds the bounded profile.');
        }

        if (preg_match('/^[a-f0-9]{64}$/', $definitionHash) !== 1) {
            throw new InvalidArgumentException('The recurrence definition hash is invalid.');
        }
    }

    /**
     * @param  list<string>  $rdates
     * @param  list<string>  $exdates
     * @param  list<RecurrenceOverride>  $overrides
     */
    public static function create(
        string $seriesId,
        string $immutableRevision,
        DateStart $start,
        ?string $rrule,
        array $rdates = [],
        array $exdates = [],
        array $overrides = [],
        ?string $tzdbVersion = null,
    ): self {
        $tzdbVersion ??= RuntimeProfile::tzdbVersion();
        $definitionHash = CanonicalHasher::definitionHash(
            seriesId: $seriesId,
            immutableRevision: $immutableRevision,
            start: $start,
            rrule: $rrule,
            rdates: $rdates,
            exdates: $exdates,
            overrides: $overrides,
            tzdbVersion: $tzdbVersion,
        );

        return new self(
            schemaVersion: 'recurrence/1',
            seriesId: $seriesId,
            immutableRevision: $immutableRevision,
            start: $start,
            rrule: $rrule,
            rdates: $rdates,
            exdates: $exdates,
            overrides: $overrides,
            tzdbVersion: $tzdbVersion,
            definitionHash: $definitionHash,
        );
    }
}
