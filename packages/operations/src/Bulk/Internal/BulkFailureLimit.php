<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use Packman\Attributes\Internal;

#[Internal]
final class BulkFailureLimit
{
    public const int DEFAULT = 50;
}
