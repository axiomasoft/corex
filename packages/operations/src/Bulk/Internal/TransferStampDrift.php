<?php

declare(strict_types=1);

namespace CoreX\Api\Internal\Bulk;

use RuntimeException;

/** @internal Changed restore epoch, extension version or rights before the next batch. */
final class TransferStampDrift extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Transfer bulk stamp drifted before the next batch.');
    }
}
