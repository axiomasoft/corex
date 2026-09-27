<?php

declare(strict_types=1);

namespace CoreX\Intl\Internal\Reference;

use CoreX\Intl\Contracts\GeographyProvider;
use CoreX\Intl\Data\DatasetManifest;
use CoreX\Intl\Data\GeographyQuery;
use CoreX\Intl\Data\ReferenceCursor;
use CoreX\Intl\Data\ReferenceIdentity;
use CoreX\Intl\Data\ReferencePage;
use CoreX\Intl\Exceptions\SnapshotExpiredException;
use CoreX\Support\Data\DataScope;

abstract class AbstractFixtureReferenceProvider implements GeographyProvider
{
    /** @var list<array<string, mixed>> */
    private array $records;

    private DatasetManifest $manifest;

    public function __construct(
        private readonly string $provider,
        private readonly string $fixturePath,
        private readonly FixtureReferenceNormalizer $normalizer,
        private readonly ReferenceCursorCodec $cursors,
    ) {
        $parsed = $this->normalizer->parse(provider: $provider, fixturePath: $fixturePath);
        $this->manifest = $parsed['manifest'];
        $this->records = $parsed['records'];
    }

    public function describe(DataScope $scope): DatasetManifest
    {
        return $this->manifest;
    }

    public function query(DataScope $scope, GeographyQuery $query, ?ReferenceCursor $after = null): ReferencePage
    {
        $snapshotId = $query->snapshotId ?? $this->manifest->snapshotId;
        $queryHash = $this->queryHash(query: $query);

        if ($snapshotId !== $this->manifest->snapshotId) {
            throw new SnapshotExpiredException('Pinned reference snapshot is no longer available.');
        }

        if ($after !== null) {
            $this->cursors->decode(cursor: $after, query: $query, snapshotId: $snapshotId, queryHash: $queryHash);
        }

        $filtered = array_values(array_filter(
            $this->records,
            fn (array $record): bool => $this->matches(query: $query, record: $record),
        ));

        usort($filtered, fn (array $a, array $b): int => [$a['normalized_name'], $a['namespace'], $a['sourceId']]
            <=> [$b['normalized_name'], $b['namespace'], $b['sourceId']]);

        if ($after !== null) {
            $filtered = array_values(array_filter(
                $filtered,
                fn (array $record): bool => [$record['normalized_name'], $record['namespace'], $record['sourceId']]
                    > [$after->lastNormalizedName, $after->lastNamespace, $after->lastSourceId],
            ));
        }

        $limit = min(max(1, $query->limit), 100);
        $page = array_slice($filtered, 0, $limit);
        $next = null;

        if (count($filtered) > $limit && $page !== []) {
            $last = $page[array_key_last($page)];
            $next = $this->cursors->encode(
                query: $query,
                snapshotId: $snapshotId,
                queryHash: $queryHash,
                position: [
                    'normalizedName' => (string) $last['normalized_name'],
                    'namespace' => (string) $last['namespace'],
                    'sourceId' => (string) $last['sourceId'],
                ],
            );
        }

        return new ReferencePage(
            status: $page === [] ? 'notFound' : 'available',
            records: array_map(fn (array $record): array => $this->present(record: $record), $page),
            next: $next,
            snapshotId: $snapshotId,
            provenance: ['provider' => $this->provider, 'snapshotId' => $snapshotId],
        );
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function matches(GeographyQuery $query, array $record): bool
    {
        if ($record['kind'] !== $query->kind) {
            return false;
        }

        if ($query->countryKey instanceof ReferenceIdentity) {
            if ($record['kind'] === 'country') {
                if ($record['namespace'] !== $query->countryKey->namespace || $record['sourceId'] !== $query->countryKey->sourceId) {
                    return false;
                }
            } else {
                $country = is_array($record['country_key'] ?? null) ? $record['country_key'] : null;

                if (! is_array($country)
                    || ($country['namespace'] ?? '') !== $query->countryKey->namespace
                    || ($country['sourceId'] ?? '') !== $query->countryKey->sourceId) {
                    return false;
                }
            }
        }

        if ($query->parentKey instanceof ReferenceIdentity) {
            if (($record['parent_namespace'] ?? '') !== $query->parentKey->namespace
                || ($record['parent_source_id'] ?? '') !== $query->parentKey->sourceId) {
                return false;
            }
        }

        if ($query->placeKey instanceof ReferenceIdentity) {
            if ($record['namespace'] !== $query->placeKey->namespace || $record['sourceId'] !== $query->placeKey->sourceId) {
                return false;
            }
        }

        if ($query->query !== null && $query->query !== '') {
            $needle = $this->normalizer->normalizeQuery(query: $query->query);

            return match ($query->mode) {
                'exact' => $record['normalized_name'] === $needle,
                'prefix' => str_starts_with((string) $record['normalized_name'], $needle),
                default => str_contains((string) $record['normalized_name'], $needle),
            };
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function present(array $record): array
    {
        return [
            'namespace' => $record['namespace'],
            'sourceId' => $record['sourceId'],
            'kind' => $record['kind'],
            'names' => $record['names'],
            'countryKey' => $record['country_key'],
            'coordinates' => $record['coordinates'],
            'timezones' => $record['timezones'],
            'provenance' => $record['provenance'],
        ];
    }

    private function queryHash(GeographyQuery $query): string
    {
        return hash('sha256', json_encode([
            'kind' => $query->kind,
            'countryKey' => $query->countryKey,
            'parentKey' => $query->parentKey,
            'placeKey' => $query->placeKey,
            'query' => $query->query,
            'locale' => $query->locale,
            'mode' => $query->mode,
            'limit' => $query->limit,
        ], JSON_THROW_ON_ERROR));
    }
}
