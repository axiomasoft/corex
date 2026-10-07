<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Internal\Reference;

use CoreX\Intl\Territory\Data\GeographyQuery;
use CoreX\Intl\Territory\Data\ReferenceCursor;
use CoreX\Intl\Territory\Internal\Value;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

final readonly class ReferenceCursorCodec
{
    public function __construct(private string $secret)
    {
        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('Reference cursor secret must contain at least 32 bytes.');
        }
    }

    /** @param array<string, string> $position */
    public function encode(GeographyQuery $query, string $snapshotId, string $queryHash, array $position): ReferenceCursor
    {
        $proof = $this->proof(
            snapshotId: $snapshotId,
            queryHash: $queryHash,
            position: $position,
        );

        return new ReferenceCursor(
            schema: 'reference-cursor/1',
            snapshotId: $snapshotId,
            queryHash: $queryHash,
            lastNormalizedName: Value::text($position['normalizedName'] ?? ''),
            lastNamespace: Value::text($position['namespace'] ?? ''),
            lastSourceId: Value::text($position['sourceId'] ?? ''),
            proof: $proof,
        );
    }

    public function decode(ReferenceCursor $cursor, GeographyQuery $query, string $snapshotId, string $queryHash): void
    {
        if ($cursor->snapshotId !== $snapshotId || ! hash_equals($queryHash, $cursor->queryHash)) {
            throw new InvalidArgumentException('Reference cursor does not match the current query snapshot.');
        }

        $expected = $this->proof(
            snapshotId: $snapshotId,
            queryHash: $queryHash,
            position: [
                'normalizedName' => $cursor->lastNormalizedName,
                'namespace' => $cursor->lastNamespace,
                'sourceId' => $cursor->lastSourceId,
            ],
        );

        if (! hash_equals($expected, $cursor->proof)) {
            throw new InvalidArgumentException('Reference cursor proof is invalid.');
        }
    }

    /**
     * @param  array<string, string>  $position
     */
    private function proof(string $snapshotId, string $queryHash, array $position): string
    {
        try {
            $payload = json_encode([
                'snapshotId' => $snapshotId,
                'queryHash' => $queryHash,
                'position' => $position,
            ], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Reference cursor proof could not be encoded.', previous: $exception);
        }

        return hash_hmac('sha256', $payload, hash('sha256', $this->secret, true));
    }
}
