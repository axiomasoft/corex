<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use Packman\Attributes\Internal;
use RuntimeException;

/** @internal Changed restore epoch, extension version or rights before the next batch. */
#[Internal]
final class TransferStampDrift extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Transfer bulk stamp drifted before the next batch.');
    }
}
