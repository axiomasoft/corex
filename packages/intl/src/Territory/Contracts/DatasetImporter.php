<?php

declare(strict_types=1);

namespace CoreX\Intl\Territory\Contracts;

use CoreX\Data\DataScope;
use CoreX\Intl\Territory\Data\DatasetCandidate;
use CoreX\Intl\Territory\Data\DatasetGeneration;
use CoreX\Intl\Territory\Data\DatasetReceipt;

interface DatasetImporter
{
    public function publish(DataScope $scope, DatasetCandidate $candidate, DatasetGeneration $expected): DatasetReceipt;
}
