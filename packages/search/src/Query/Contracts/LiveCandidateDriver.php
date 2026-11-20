<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Search\Index\Data\IndexTarget;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface LiveCandidateDriver extends CandidateDriver
{
    public function target(DataScope $scope, EntityHandle $entity, string $mappingDigest): IndexTarget;
}
