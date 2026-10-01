<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Data;

use CoreX\Attributes\Api;
use DateTimeImmutable;

#[Api(since: '1.0')]
final readonly class DatasetManifest
{
    /**
     * @param  list<string>  $coveredCountries
     * @param  list<string>  $types
     * @param  array<string,int>  $counts
     */
    public function __construct(
        public string $schema,
        public string $snapshotId,
        public string $provider,
        public string $sourceUri,
        public DateTimeImmutable $retrievedAt,
        public string $rawHash,
        public string $normalizedHash,
        public string $sourceVersion,
        public string $importerVersion,
        public string $licenseUri,
        public string $attribution,
        public array $coveredCountries,
        public array $types,
        public array $counts,
        public string $coverageNotes,
        public string $tzdbVersion,
        public string $status,
    ) {}
}
