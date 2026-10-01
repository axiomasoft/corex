<?php

declare(strict_types=1);

namespace CoreX\Intl\Currency\Laravel;

use Brick\Math\RoundingMode;
use Brick\Money\Context;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class ConversionPolicy
{
    public function __construct(
        public RoundingMode $roundingMode,
        public Context $targetContext,
        public string $policyId,
        public bool $allowStale,
    ) {}
}
