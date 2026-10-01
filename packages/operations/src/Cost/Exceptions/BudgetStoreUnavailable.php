<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Exceptions;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use RuntimeException;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class BudgetStoreUnavailable extends RuntimeException {}
