<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Search\Projection\Data\RecordBatch;
use CoreX\Search\Projection\Data\RecordRefs;
use CoreX\Search\Projection\Data\SourceRecords;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface SearchSource
{
    /** Ascending bytewise RecordRef ID keyset in this scope. Next is the last returned ref when more rows remain. */
    public function page(DataScope $scope, EntityHandle $entity, ?RecordRef $after, int $limit): RecordBatch;

    public function load(DataScope $scope, RecordRefs $records): SourceRecords;
}
