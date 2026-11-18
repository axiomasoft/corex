<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal;

use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final class InvalidAttributeValue extends InvalidArgumentException {}
