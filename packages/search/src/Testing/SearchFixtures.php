<?php

declare(strict_types=1);

namespace CoreX\Search\Testing;

use CoreX\Data\Data\RecordRef;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\SourceRecord;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class SearchFixtures
{
    /** @param array<string,string|bool|int|null> $values */
    public static function record(RecordRef $record, array $values): SourceRecord
    {
        ksort($values, SORT_STRING);

        return new SourceRecord(record: $record, revision: hash('sha256', json_encode($values, JSON_THROW_ON_ERROR)), values: new ProjectedValues($values));
    }
}
