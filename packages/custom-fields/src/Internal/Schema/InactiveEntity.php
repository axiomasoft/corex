<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Schema;

use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final class InactiveEntity extends RuntimeException {}
