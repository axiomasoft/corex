<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Contracts;

use CoreX\Data\Data\DataScope;
use CoreX\Intl\Territory\Data\DatasetCandidate;
use CoreX\Intl\Territory\Data\DatasetGeneration;
use CoreX\Intl\Territory\Data\DatasetReceipt;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface DatasetImporter
{
    public function publish(DataScope $scope, DatasetCandidate $candidate, DatasetGeneration $expected): DatasetReceipt;
}
