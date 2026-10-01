<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data;

use CoreX\Attributes\Api;
use DateTimeImmutable;
use InvalidArgumentException;

/** @phpstan-import-type JsonMap from \CoreX\Transfer\Data\SourceRow */
#[Api(since: '1.0')]
final readonly class CanonicalRecord
{
    /** @param JsonMap $values */
    public function __construct(public string $entity, public string $externalSystem, public string $externalId, public string $sourceRevision, public ?DateTimeImmutable $sourceUpdatedAt, public array $values, public string $payloadHash)
    {
        if (preg_match('/\\A[a-z][a-z0-9._-]{0,127}\\z/D', $entity) !== 1) {
            throw new InvalidArgumentException('Canonical entity must be a registered opaque handle.');
        }
        Validation::opaque(value: $externalSystem, name: 'external system');
        Validation::opaque(value: $externalId, name: 'external id');
        Validation::opaque(value: $sourceRevision, name: 'source revision');
        Validation::utc(value: $sourceUpdatedAt, name: 'source updated time');
        Validation::jsonMap(value: $values, name: 'canonical values');
        Validation::sha256(value: $payloadHash, name: 'payload hash');
    }
}
