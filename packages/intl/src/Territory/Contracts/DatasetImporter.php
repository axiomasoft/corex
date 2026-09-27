<?php

declare(strict_types=1);

namespace CoreX\Intl\Contracts;

use CoreX\Intl\Data\DatasetCandidate;
use CoreX\Intl\Data\DatasetGeneration;
use CoreX\Intl\Data\DatasetReceipt;
use CoreX\Support\Data\DataScope;

interface DatasetImporter
{
    public function publish(DataScope $scope, DatasetCandidate $candidate, DatasetGeneration $expected): DatasetReceipt;
}
