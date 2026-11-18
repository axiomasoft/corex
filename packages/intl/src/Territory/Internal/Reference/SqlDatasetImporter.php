<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Reference;

use CoreX\Data\Contracts\DataScopeAuthorizer;
use CoreX\Data\Data\DataScope;
use CoreX\Intl\Territory\Contracts\DatasetImporter;
use CoreX\Intl\Territory\Data\DatasetCandidate;
use CoreX\Intl\Territory\Data\DatasetGeneration;
use CoreX\Intl\Territory\Data\DatasetReceipt;
use CoreX\Intl\Territory\Exceptions\AccessDeniedException;
use CoreX\Intl\Territory\Exceptions\OperationConflictException;
use CoreX\Intl\Territory\Exceptions\SchemaMismatchException;
use CoreX\Intl\Territory\Internal\Value;
use DateTimeZone;
use Illuminate\Database\Connection;
use Override;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class SqlDatasetImporter implements DatasetImporter
{
    public function __construct(
        private Connection $connection,
        private string $datasetKey,
        private FixtureReferenceNormalizer $normalizer,
        private DataScopeAuthorizer $authorizer,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function publish(DataScope $scope, DatasetCandidate $candidate, DatasetGeneration $expected): DatasetReceipt
    {
        if (! $this->authorizer->authorize(scope: $scope, entity: 'reference-publish', subjectId: $candidate->manifest->snapshotId, fields: [], action: 'publish')->allowed) {
            throw new AccessDeniedException('Reference dataset publication is not authorized.');
        }

        $parsed = $this->normalizer->parse(
            provider: $candidate->manifest->provider,
            fixturePath: $candidate->immutableRef,
        );

        if (! hash_equals($candidate->manifest->rawHash, $parsed['rawHash'])) {
            throw new SchemaMismatchException('Dataset candidate raw hash does not match the fixture contents.');
        }

        if (! hash_equals($candidate->manifest->normalizedHash, $parsed['normalizedHash'])) {
            throw new SchemaMismatchException('Dataset candidate normalized hash does not match the fixture contents.');
        }

        $datasetKey = $this->datasetKey;
        $snapshotId = $candidate->manifest->snapshotId;

        return $this->connection->transaction(function () use ($datasetKey, $snapshotId, $candidate, $expected, $parsed): DatasetReceipt {
            $current = $this->connection->table('corex_reference_current')
                ->where('dataset_key', $datasetKey)
                ->lockForUpdate()
                ->first();

            $currentGeneration = $current !== null ? Value::integer($current->generation) : 0;

            if ($expected->expectedSnapshot !== null && $current !== null && $current->snapshot_id !== $expected->expectedSnapshot) {
                throw new OperationConflictException('Reference dataset expected snapshot does not match the current pointer.');
            }

            if ($expected->generation !== $currentGeneration) {
                return new DatasetReceipt(
                    status: 'conflict',
                    snapshotId: null,
                    generation: $currentGeneration,
                    errorCode: 'generation_mismatch',
                );
            }

            $existing = $this->connection->table('corex_reference_datasets')
                ->where('snapshot_id', $snapshotId)
                ->first();

            if ($existing !== null) {
                if (! hash_equals(Value::text($existing->normalized_hash), $candidate->manifest->normalizedHash)) {
                    throw new OperationConflictException('Reference snapshot identity already exists with different content.');
                }

                return new DatasetReceipt(
                    status: 'noop',
                    snapshotId: $snapshotId,
                    generation: $currentGeneration,
                    errorCode: null,
                );
            }

            $this->validateRecords(records: $parsed['records']);

            $manifestJson = json_encode([
                'schema' => $candidate->manifest->schema,
                'provider' => $candidate->manifest->provider,
                'sourceUri' => $candidate->manifest->sourceUri,
                'retrievedAt' => $candidate->manifest->retrievedAt->format('c'),
                'sourceVersion' => $candidate->manifest->sourceVersion,
                'importerVersion' => $candidate->manifest->importerVersion,
                'licenseUri' => $candidate->manifest->licenseUri,
                'attribution' => $candidate->manifest->attribution,
                'coveredCountries' => $candidate->manifest->coveredCountries,
                'types' => $candidate->manifest->types,
                'counts' => $candidate->manifest->counts,
                'coverageNotes' => $candidate->manifest->coverageNotes,
                'tzdbVersion' => $candidate->manifest->tzdbVersion,
            ], JSON_THROW_ON_ERROR);

            $this->connection->table('corex_reference_datasets')->insert([
                'snapshot_id' => $snapshotId,
                'manifest' => $manifestJson,
                'raw_hash' => $candidate->manifest->rawHash,
                'normalized_hash' => $candidate->manifest->normalizedHash,
                'state' => 'published',
                'created_at' => $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            ]);

            foreach ($parsed['records'] as $record) {
                $this->connection->table('corex_reference_records')->insert([
                    'snapshot_id' => $snapshotId,
                    'namespace' => $record['namespace'],
                    'source_id' => $record['sourceId'],
                    'kind' => $record['kind'],
                    'parent_namespace' => $record['parent_namespace'],
                    'parent_source_id' => $record['parent_source_id'],
                    'names' => json_encode($record['names'], JSON_THROW_ON_ERROR),
                    'normalized_name' => $record['normalized_name'],
                    'country_key' => ($record['country_key'] !== null && $record['country_key'] !== []) ? json_encode($record['country_key'], JSON_THROW_ON_ERROR) : null,
                    'coordinates' => ($record['coordinates'] !== null && $record['coordinates'] !== []) ? json_encode($record['coordinates'], JSON_THROW_ON_ERROR) : null,
                    'timezones' => json_encode($record['timezones'], JSON_THROW_ON_ERROR),
                    'provenance' => json_encode($record['provenance'], JSON_THROW_ON_ERROR),
                ]);

                foreach (Value::array($record['aliases']) as $alias) {
                    if (! is_array($alias)) {
                        continue;
                    }

                    $this->connection->table('corex_reference_aliases')->insert([
                        'snapshot_id' => $snapshotId,
                        'namespace' => $record['namespace'],
                        'source_id' => $record['sourceId'],
                        'locale' => Value::text($alias['locale'] ?? 'en'),
                        'alias' => Value::text($alias['alias'] ?? ''),
                        'normalized_alias' => $this->normalizer->normalizeName(name: Value::text($alias['alias'] ?? '')),
                    ]);
                }
            }

            $nextGeneration = $currentGeneration + 1;

            if ($current === null) {
                $this->connection->table('corex_reference_current')->insert([
                    'dataset_key' => $datasetKey,
                    'snapshot_id' => $snapshotId,
                    'generation' => $nextGeneration,
                ]);
            } else {
                $updated = $this->connection->table('corex_reference_current')
                    ->where('dataset_key', $datasetKey)
                    ->where('generation', $currentGeneration)
                    ->update([
                        'snapshot_id' => $snapshotId,
                        'generation' => $nextGeneration,
                    ]);

                if ($updated !== 1) {
                    throw new OperationConflictException('Reference dataset pointer CAS update failed.');
                }
            }

            return new DatasetReceipt(
                status: 'published',
                snapshotId: $snapshotId,
                generation: $nextGeneration,
                errorCode: null,
            );
        });
    }

    /**
     * @param  list<array<string, mixed>>  $records
     */
    private function validateRecords(array $records): void
    {
        $seen = [];

        foreach ($records as $record) {
            $key = Value::text($record['namespace']).'|'.Value::text($record['sourceId']);

            if (isset($seen[$key])) {
                throw new SchemaMismatchException('Duplicate reference identity in dataset candidate.');
            }

            $seen[$key] = true;

            if (($record['parent_namespace'] ?? null) === null xor ($record['parent_source_id'] ?? null) === null) {
                throw new SchemaMismatchException('Reference parent identity must be fully specified or absent.');
            }
        }
    }
}
