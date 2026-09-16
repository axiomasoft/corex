<?php

declare(strict_types=1);

namespace CoreX\Media\Data;

final readonly class ConversionOperationRequest
{
    public function __construct(public string $fileId, public string $profileKey) {}
}
