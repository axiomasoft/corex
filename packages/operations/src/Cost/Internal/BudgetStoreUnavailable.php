<?php

declare(strict_types=1);

namespace CoreX\Operations\Cost\Internal;

use RuntimeException;

/** @internal Keeps budget storage failure distinct from an enforced 429. */
final class BudgetStoreUnavailable extends RuntimeException {}
