<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final class KeyConflict extends RuntimeException {}
