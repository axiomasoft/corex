<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\WriteToken;
use CoreX\Intl\Currency\Data\RateBatch;
use CoreX\Intl\Currency\Data\RateImportReceipt;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface RateImporter
{
    public function import(DataScope $scope, RateBatch $batch, WriteToken $token): RateImportReceipt;
}
