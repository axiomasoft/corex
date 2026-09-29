<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Data;

use Brick\Money\Money;

final readonly class ConversionReceipt
{
    public function __construct(
        public Money $source,
        public Money $result,
        public string $rateSnapshotId,
        public string $rateHash,
        public string $policyHash,
        public string $requestedDate,
    ) {}
}
