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
use CoreX\Intl\Territory\Internal\Value;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Override;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final readonly class SqlGeographyProvider implements GeographyProvider
{
    public function __construct(
        private Connection $connection,
        private string $datasetKey,
        private FixtureReferenceNormalizer $normalizer,
        private ReferenceCursorCodec $cursors,
    ) {}

    #[Override]
    public function describe(DataScope $scope): DatasetManifest
    {
        $datasetKey = $this->datasetKey;
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
        $datasetKey = $this->datasetKey;
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
            $this->cursors->decode(cursor: $after, query: $query, snapshotId: Value::text($snapshotId), queryHash: $queryHash);
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
            $builder->where(function (Builder $nested) use ($after): void {
                $nested->where('normalized_name', '>', $after->lastNormalizedName)
                    ->orWhere(function (Builder $inner) use ($after): void {
                        $inner->where('normalized_name', $after->lastNormalizedName)
                            ->where(function (Builder $tie) use ($after): void {
                                $tie->where('namespace', '>', $after->lastNamespace)
                                    ->orWhere(function (Builder $last) use ($after): void {
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
                snapshotId: Value::text($snapshotId),
                queryHash: $queryHash,
                position: [
                    'normalizedName' => Value::text($last->normalized_name),
                    'namespace' => Value::text($last->namespace),
                    'sourceId' => Value::text($last->source_id),
                ],
            );
        }

        return new ReferencePage(
            status: $records === [] ? 'notFound' : 'available',
            records: JsonDocuments::capture($records),
            next: $next,
            snapshotId: Value::nullableText($snapshotId),
            provenance: JsonDocuments::capture(['datasetKey' => $datasetKey, 'snapshotId' => $snapshotId]),
        );
    }

    private function manifestFromRow(stdClass $row): DatasetManifest
    {
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode(Value::text($row->manifest), true, 16, JSON_THROW_ON_ERROR);

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
            schema: Value::text($manifest['schema'] ?? 'reference-data/1'),
            snapshotId: Value::text($row->snapshot_id),
            provider: Value::text($manifest['provider'] ?? 'sql'),
            sourceUri: Value::text($manifest['sourceUri'] ?? ''),
            retrievedAt: new DateTimeImmutable(Value::text($manifest['retrievedAt'] ?? $row->created_at)),
            rawHash: Value::text($row->raw_hash),
            normalizedHash: Value::text($row->normalized_hash),
            sourceVersion: Value::text($manifest['sourceVersion'] ?? '1'),
            importerVersion: Value::text($manifest['importerVersion'] ?? 'intl/1'),
            licenseUri: Value::text($manifest['licenseUri'] ?? ''),
            attribution: Value::text($manifest['attribution'] ?? ''),
            coveredCountries: Value::strings($manifest['coveredCountries'] ?? []),
            types: array_values($types),
            counts: $counts,
            coverageNotes: Value::text($manifest['coverageNotes'] ?? ''),
            tzdbVersion: Value::text($manifest['tzdbVersion'] ?? ''),
            status: Value::text($row->state),
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
            'names' => json_decode(Value::text($row->names), true),
            'countryKey' => $row->country_key ? json_decode(Value::text($row->country_key), true) : null,
            'coordinates' => $row->coordinates ? json_decode(Value::text($row->coordinates), true) : null,
            'timezones' => json_decode(Value::text($row->timezones), true),
            'provenance' => json_decode(Value::text($row->provenance), true),
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
