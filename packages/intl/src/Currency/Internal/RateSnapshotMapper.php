<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Internal;

use CoreX\Intl\Currency\Data\RateSnapshot;
use DateTimeImmutable;
use Packman\Attributes\Internal;
use stdClass;

#[Internal]
final class RateSnapshotMapper
{
    public static function fromRow(stdClass $row): RateSnapshot
    {
        return new RateSnapshot(
            schema: Value::text($row->schema_version),
            base: Value::text($row->base),
            quote: Value::text($row->quote),
            rateDecimal: Value::text($row->rate_decimal),
            validOn: Value::text($row->valid_on),
            observedAt: new DateTimeImmutable(Value::text($row->observed_at)),
            source: Value::text($row->source),
            publicationId: Value::text($row->publication_id),
            publicationRevision: Value::text($row->publication_revision),
            payloadSha256: Value::text($row->payload_hash),
            id: Value::text($row->id),
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
