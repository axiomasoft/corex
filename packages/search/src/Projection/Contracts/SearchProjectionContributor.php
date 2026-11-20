<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Contracts;

use CoreX\Data\Data\RecordRef;
use CoreX\Search\Projection\Data\ProjectedValues;
use CoreX\Search\Projection\Data\ProjectionContext;
use CoreX\Search\Projection\Data\ProjectionFields;
use CoreX\Search\Projection\Data\ProjectionSelection;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface SearchProjectionContributor
{
    public function describe(ProjectionContext $context): ProjectionFields;

    public function project(ProjectionContext $context, RecordRef $record, ProjectionSelection $selection): ProjectedValues;
}
