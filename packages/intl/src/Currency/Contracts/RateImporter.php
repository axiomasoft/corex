<?php

declare(strict_types=1);

namespace CoreX\Intl\Contracts;

use CoreX\Intl\Data\RateBatch;
use CoreX\Intl\Data\RateImportReceipt;
use CoreX\Support\Data\DataScope;
use CoreX\Support\Data\WriteToken;

interface RateImporter
{
    public function import(DataScope $scope, RateBatch $batch, WriteToken $token): RateImportReceipt;
}
