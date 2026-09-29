<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Internal;

use CoreX\Intl\Currency\Contracts\RateProvider;
use CoreX\Intl\Currency\Data\RateLookup;
use CoreX\Intl\Currency\Data\RateQuery;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Override;

final class SqlRateProvider implements RateProvider
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    #[Override]
    public function lookup(RateQuery $query): RateLookup
    {
        if ($query->sourceSelection === []) {
            return new RateLookup(status: 'unsupported', snapshot: null);
        }

        $source = $query->sourceSelection[0];

        $row = $this->connection->table('corex_rate_snapshots')
            ->where('base', strtoupper($query->base))
            ->where('quote', strtoupper($query->quote))
            ->where('source', $source)
            ->where('valid_on', '<=', $query->onDate)
            ->when(
                $query->publicationCutoff !== null,
                fn ($builder) => $builder->where('observed_at', '<=', $query->publicationCutoff->format('Y-m-d H:i:s')),
            )
            ->orderByDesc('valid_on')
            ->orderByDesc('observed_at')
            ->orderByDesc('publication_revision')
            ->first();

        if ($row === null) {
            $future = $this->connection->table('corex_rate_snapshots')
                ->where('base', strtoupper($query->base))
                ->where('quote', strtoupper($query->quote))
                ->where('source', $source)
                ->where('valid_on', '>', $query->onDate)
                ->exists();

            return new RateLookup(status: $future ? 'missing' : 'missing', snapshot: null);
        }

        $snapshot = RateSnapshotMapper::fromRow(row: $row);
        $ageDays = (new DateTimeImmutable($query->onDate))->diff(new DateTimeImmutable($snapshot->validOn))->days ?? 0;

        if ($ageDays > $query->maxAgeDays) {
            if (! $query->allowStale) {
                return new RateLookup(status: 'stale', snapshot: $snapshot);
            }
        }

        return new RateLookup(status: 'found', snapshot: $snapshot);
    }
}
