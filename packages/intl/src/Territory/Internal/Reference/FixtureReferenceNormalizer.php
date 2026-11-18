<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Reference;

use CoreX\Intl\Territory\Data\DatasetManifest;
use CoreX\Intl\Territory\Exceptions\SchemaMismatchException;
use CoreX\Intl\Territory\Internal\Value;
use DateTimeImmutable;
use JsonException;
use Packman\Attributes\Internal;

#[Internal]
final class FixtureReferenceNormalizer
{
    /**
     * @return array{manifest: DatasetManifest, records: list<array<string, mixed>>, rawHash: string, normalizedHash: string}
     */
    public function parse(string $provider, string $fixturePath): array
    {
        if (! is_file($fixturePath)) {
            throw new SchemaMismatchException('Reference fixture file is missing.');
        }

        $raw = file_get_contents($fixturePath);

        if ($raw === false || $raw === '') {
            throw new SchemaMismatchException('Reference fixture file is empty.');
        }

        if (strlen($raw) > 512 * 1024 * 1024) {
            throw new SchemaMismatchException('Reference fixture exceeds the maximum import size.');
        }

        $rawHash = hash('sha256', $raw);

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SchemaMismatchException('Reference fixture JSON is malformed.');
        }

        if (! is_array($decoded) || ! is_array($decoded['records'] ?? null)) {
            throw new SchemaMismatchException('Reference fixture must contain a records array.');
        }

        $records = [];

        foreach ($decoded['records'] as $record) {
            if (! is_array($record)) {
                throw new SchemaMismatchException('Reference fixture record must be an object.');
            }

            $records[] = $this->normalizeRecord(record: Value::map($record), provider: $provider);
        }

        $normalizedHash = hash('sha256', json_encode($records, JSON_THROW_ON_ERROR));

        $manifest = new DatasetManifest(
            schema: 'reference-data/1',
            snapshotId: Value::text($decoded['snapshotId'] ?? $provider.'-fixture-v1'),
            provider: $provider,
            sourceUri: Value::text($decoded['sourceUri'] ?? 'fixture://'.$provider),
            retrievedAt: new DateTimeImmutable(Value::text($decoded['retrievedAt'] ?? '2026-09-15T00:00:00+00:00')),
            rawHash: $rawHash,
            normalizedHash: $normalizedHash,
            sourceVersion: Value::text($decoded['sourceVersion'] ?? '1.0.0'),
            importerVersion: Value::text($decoded['importerVersion'] ?? 'intl/1'),
            licenseUri: Value::text($decoded['licenseUri'] ?? 'https://corex.test/licenses/fixture'),
            attribution: Value::text($decoded['attribution'] ?? 'CoreX test fixture'),
            coveredCountries: array_values(array_unique(array_map(
                static fn (array $row): string => Value::text((is_array($row['countryKey'] ?? null) ? ($row['countryKey']['sourceId'] ?? null) : null) ?? $row['sourceId'] ?? ''),
                array_filter($records, static fn (array $row): bool => ($row['kind'] ?? '') === 'country'),
            ))),
            types: array_values(array_unique(array_map(static fn (array $row): string => Value::text($row['kind']), $records))),
            counts: array_count_values(array_map(static fn (array $row): string => Value::text($row['kind']), $records)),
            coverageNotes: Value::text($decoded['coverageNotes'] ?? 'Licensed minimal fixture for conformance tests.'),
            tzdbVersion: Value::text($decoded['tzdbVersion'] ?? '2025a'),
            status: 'published',
        );

        return [
            'manifest' => $manifest,
            'records' => $records,
            'rawHash' => $rawHash,
            'normalizedHash' => $normalizedHash,
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function normalizeRecord(array $record, string $provider): array
    {
        $namespace = Value::text($record['namespace'] ?? '');
        $sourceId = Value::text($record['sourceId'] ?? '');
        $kind = Value::text($record['kind'] ?? '');

        if ($namespace === '' || $sourceId === '' || $kind === '') {
            throw new SchemaMismatchException('Reference record requires namespace, sourceId, and kind.');
        }

        $names = is_array($record['names'] ?? null) ? $record['names'] : [];
        $primaryName = is_string($names['en'] ?? null) ? $names['en'] : Value::text(reset($names));

        return [
            'namespace' => $namespace,
            'sourceId' => $sourceId,
            'kind' => $kind,
            'parent_namespace' => is_array($record['parent'] ?? null) ? Value::text($record['parent']['namespace'] ?? '') : null,
            'parent_source_id' => is_array($record['parent'] ?? null) ? Value::text($record['parent']['sourceId'] ?? '') : null,
            'names' => $names,
            'normalized_name' => $this->normalizeName(name: $primaryName),
            'country_key' => $record['countryKey'] ?? null,
            'coordinates' => $record['coordinates'] ?? null,
            'timezones' => is_array($record['timezones'] ?? null) ? $record['timezones'] : [],
            'provenance' => ['provider' => $provider, 'sourceId' => $sourceId],
            'aliases' => is_array($record['aliases'] ?? null) ? $record['aliases'] : [],
        ];
    }

    public function normalizeName(string $name, string $locale = 'en'): string
    {
        $trimmed = trim(mb_strtolower($name, 'UTF-8'));

        if (strlen($trimmed) > 255) {
            throw new SchemaMismatchException('Reference name exceeds the maximum length.');
        }

        return $trimmed;
    }

    public function normalizeQuery(string $query): string
    {
        $trimmed = trim(mb_strtolower($query, 'UTF-8'));

        if ($trimmed === '' || strlen($trimmed) > 200) {
            throw new SchemaMismatchException('Reference query must be between 1 and 200 characters.');
        }

        return $trimmed;
    }
}
