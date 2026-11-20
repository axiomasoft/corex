<?php

declare(strict_types=1);

namespace CoreX\Workflow\Execution\Internal;

use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final class UnsupportedWorkflowCapability extends RuntimeException {}
