<?php

declare(strict_types=1);

namespace CoreX\Media\Internal\Data;

use Packman\Attributes\Internal;

#[Internal]
final readonly class ConversionOperationRequest
{
    public function __construct(public string $fileId, public string $profileKey, public string $operationKey) {}
}
