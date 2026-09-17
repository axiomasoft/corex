<?php

declare(strict_types=1);

namespace CoreX\Intl\Data;

use Brick\Math\RoundingMode;
use Brick\Money\Context;

final readonly class ConversionPolicy
{
    public function __construct(
        public RoundingMode $roundingMode,
        public Context $targetContext,
        public string $policyId,
        public bool $allowStale,
    ) {}
}
