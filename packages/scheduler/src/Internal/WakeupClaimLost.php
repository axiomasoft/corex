<?php

declare(strict_types=1);

namespace CoreX\Scheduler\Internal;

use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final class WakeupClaimLost extends RuntimeException {}
