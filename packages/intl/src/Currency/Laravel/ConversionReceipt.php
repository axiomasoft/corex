<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Laravel;

use Brick\Money\Money;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
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
