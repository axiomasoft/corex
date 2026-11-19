<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\Permissions\PolicyOnly;
use Packman\Attributes\Internal;

/** Product permission for evaluating one CoreX role-matrix cell. */
#[Internal]
#[PolicyOnly]
enum MatrixPermission: string
{
    case Evaluate = 'matrix.evaluate';
}
