<?php

declare(strict_types=1);

namespace CoreX\Workflow\Internal;

use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final class UnsupportedWorkflowCapability extends RuntimeException {}
