<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Contracts;

use CoreX\Data\DataScope;
use CoreX\Data\WriteToken;
use CoreX\Intl\Currency\Data\RateBatch;
use CoreX\Intl\Currency\Data\RateImportReceipt;

interface RateImporter
{
    public function import(DataScope $scope, RateBatch $batch, WriteToken $token): RateImportReceipt;
}
