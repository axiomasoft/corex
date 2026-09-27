<?php

declare(strict_types=1);

namespace CoreX\Intl\Internal\Money;

use CoreX\Intl\Data\RateSnapshot;
use DateTimeImmutable;
use stdClass;

final class RateSnapshotMapper
{
    public static function fromRow(stdClass $row): RateSnapshot
    {
        return new RateSnapshot(
            schema: (string) $row->schema_version,
            base: (string) $row->base,
            quote: (string) $row->quote,
            rateDecimal: (string) $row->rate_decimal,
            validOn: (string) $row->valid_on,
            observedAt: new DateTimeImmutable((string) $row->observed_at),
            source: (string) $row->source,
            publicationId: (string) $row->publication_id,
            publicationRevision: (string) $row->publication_revision,
            payloadSha256: (string) $row->payload_hash,
            id: (string) $row->id,
        );
    }

    public static function payloadHash(RateSnapshot $snapshot): string
    {
        return hash('sha256', implode("\0", [
            $snapshot->schema,
            $snapshot->base,
            $snapshot->quote,
            $snapshot->rateDecimal,
            $snapshot->validOn,
            $snapshot->observedAt->format('Y-m-d\TH:i:s\Z'),
            $snapshot->source,
            $snapshot->publicationId,
            $snapshot->publicationRevision,
        ]));
    }
}
