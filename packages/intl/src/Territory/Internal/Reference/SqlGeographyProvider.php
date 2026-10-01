<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Reference;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Intl\Currency\Exceptions\SnapshotExpiredException;
use CoreX\Intl\Territory\Contracts\GeographyProvider;
use CoreX\Intl\Territory\Data\DatasetManifest;
use CoreX\Intl\Territory\Data\GeographyQuery;
use CoreX\Intl\Territory\Data\ReferenceCursor;
use CoreX\Intl\Territory\Data\ReferencePage;
use DateTimeImmutable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use InvalidArgumentException;
use Override;
use stdClass;

final class SqlGeographyProvider implements GeographyProvider
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Repository $config,
        private readonly FixtureReferenceNormalizer $normalizer,
        private readonly ReferenceCursorCodec $cursors,
    ) {}

    #[Override]
    public function describe(DataScope $scope): DatasetManifest
    {
        $datasetKey = (string) $this->config->get('corex-intl.reference.dataset_key', 'geography');
        $current = $this->connection->table('corex_reference_current')
            ->where('dataset_key', $datasetKey)
            ->first();

        if ($current === null) {
            throw new SnapshotExpiredException('No published reference dataset is available.');
        }

        $dataset = $this->connection->table('corex_reference_datasets')
            ->where('snapshot_id', $current->snapshot_id)
            ->where('state', 'published')
            ->first();

        if ($dataset === null) {
            throw new SnapshotExpiredException('Published reference dataset manifest is unavailable.');
        }

        return $this->manifestFromRow(row: $dataset);
    }

    #[Override]
    public function query(DataScope $scope, GeographyQuery $query, ?ReferenceCursor $after = null): ReferencePage
    {
        $datasetKey = (string) $this->config->get('corex-intl.reference.dataset_key', 'geography');
        $snapshotId = $query->snapshotId;

        if ($snapshotId === null) {
            $current = $this->connection->table('corex_reference_current')
                ->where('dataset_key', $datasetKey)
                ->first();
            $snapshotId = $current?->snapshot_id;
        }

        if ($snapshotId === null) {
            return new ReferencePage(status: 'snapshotExpired', records: JsonDocuments::capture([]), next: null, snapshotId: null, provenance: JsonDocuments::capture([]));
        }

        $exists = $this->connection->table('corex_reference_datasets')
            ->where('snapshot_id', $snapshotId)
            ->where('state', 'published')
            ->exists();

        if (! $exists) {
            throw new SnapshotExpiredException('Pinned reference snapshot is no longer available.');
        }

        $queryHash = $this->queryHash(query: $query);

        if ($after !== null) {
            $this->cursors->decode(cursor: $after, query: $query, snapshotId: $snapshotId, queryHash: $queryHash);
        }

        $builder = $this->connection->table('corex_reference_records')
            ->where('snapshot_id', $snapshotId)
            ->where('kind', $query->kind);

        if ($query->countryKey !== null) {
            $builder->whereRaw("json_extract(country_key, '$.namespace') = ?", [$query->countryKey->namespace])
                ->whereRaw("json_extract(country_key, '$.sourceId') = ?", [$query->countryKey->sourceId]);
        }

        if ($query->parentKey !== null) {
            $builder->where('parent_namespace', $query->parentKey->namespace)
                ->where('parent_source_id', $query->parentKey->sourceId);
        }

        if ($query->placeKey !== null) {
            $builder->where('namespace', $query->placeKey->namespace)
                ->where('source_id', $query->placeKey->sourceId);
        }

        if ($query->query !== null && $query->query !== '') {
            $needle = $this->normalizer->normalizeQuery(query: $query->query);

            $builder = match ($query->mode) {
                'exact' => $builder->where('normalized_name', $needle),
                'prefix' => $builder->where('normalized_name', 'like', $needle.'%'),
                default => $builder->where('normalized_name', 'like', '%'.$needle.'%'),
            };
        }

        if ($after !== null) {
            $builder->where(function ($nested) use ($after): void {
                $nested->where('normalized_name', '>', $after->lastNormalizedName)
                    ->orWhere(function ($inner) use ($after): void {
                        $inner->where('normalized_name', $after->lastNormalizedName)
                            ->where(function ($tie) use ($after): void {
                                $tie->where('namespace', '>', $after->lastNamespace)
                                    ->orWhere(function ($last) use ($after): void {
                                        $last->where('namespace', $after->lastNamespace)
                                            ->where('source_id', '>', $after->lastSourceId);
                                    });
                            });
                    });
            });
        }

        $limit = min(max(1, $query->limit), 100);
        $rows = $builder
            ->orderBy('normalized_name')
            ->orderBy('namespace')
            ->orderBy('source_id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $rows->count() > $limit;
        $pageRows = $rows->take($limit);
        $records = $pageRows->map(fn (stdClass $row): array => $this->present(row: $row))->all();

        $next = null;

        if ($hasMore && $pageRows->isNotEmpty()) {
            $last = $pageRows->last();
            $next = $this->cursors->encode(
                query: $query,
                snapshotId: $snapshotId,
                queryHash: $queryHash,
                position: [
                    'normalizedName' => (string) $last->normalized_name,
                    'namespace' => (string) $last->namespace,
                    'sourceId' => (string) $last->source_id,
                ],
            );
        }

        return new ReferencePage(
            status: $records === [] ? 'notFound' : 'available',
            records: JsonDocuments::capture($records),
            next: $next,
            snapshotId: $snapshotId,
            provenance: JsonDocuments::capture(['datasetKey' => $datasetKey, 'snapshotId' => $snapshotId]),
        );
    }

    private function manifestFromRow(stdClass $row): DatasetManifest
    {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode((string) $row->manifest, true, 16, JSON_THROW_ON_ERROR);

        $types = (array) ($manifest['types'] ?? []);
        foreach ($types as $type) {
            if (! is_string($type)) {
                throw new InvalidArgumentException('Dataset types must be strings.');
            }
        }
        $counts = (array) ($manifest['counts'] ?? []);
        foreach ($counts as $key => $count) {
            if (! is_string($key) || ! is_int($count)) {
                throw new InvalidArgumentException('Dataset counts must be named integers.');
            }
        }

        /** @var array<string, int> $counts */
        return new DatasetManifest(
            schema: (string) ($manifest['schema'] ?? 'reference-data/1'),
            snapshotId: (string) $row->snapshot_id,
            provider: (string) ($manifest['provider'] ?? 'sql'),
            sourceUri: (string) ($manifest['sourceUri'] ?? ''),
            retrievedAt: new DateTimeImmutable((string) ($manifest['retrievedAt'] ?? $row->created_at)),
            rawHash: (string) $row->raw_hash,
            normalizedHash: (string) $row->normalized_hash,
            sourceVersion: (string) ($manifest['sourceVersion'] ?? '1'),
            importerVersion: (string) ($manifest['importerVersion'] ?? 'intl/1'),
            licenseUri: (string) ($manifest['licenseUri'] ?? ''),
            attribution: (string) ($manifest['attribution'] ?? ''),
            coveredCountries: (array) ($manifest['coveredCountries'] ?? []),
            types: array_values($types),
            counts: $counts,
            coverageNotes: (string) ($manifest['coverageNotes'] ?? ''),
            tzdbVersion: (string) ($manifest['tzdbVersion'] ?? ''),
            status: (string) $row->state,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(stdClass $row): array
    {
        return [
            'namespace' => $row->namespace,
            'sourceId' => $row->source_id,
            'kind' => $row->kind,
            'names' => json_decode((string) $row->names, true),
            'countryKey' => $row->country_key ? json_decode((string) $row->country_key, true) : null,
            'coordinates' => $row->coordinates ? json_decode((string) $row->coordinates, true) : null,
            'timezones' => json_decode((string) $row->timezones, true),
            'provenance' => json_decode((string) $row->provenance, true),
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
