<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final class SchemaLockBusy extends RuntimeException {}
