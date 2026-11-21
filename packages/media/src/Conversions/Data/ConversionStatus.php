<?php

declare(strict_types=1);

namespace CoreX\Media\Conversions\Data;

use CoreX\Media\Conversions\Enums\ConversionState;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ConversionStatus
{
    public function __construct(public ConversionState $state, public ?string $blobRef = null, public ?string $sha256 = null, public ?int $width = null, public ?int $height = null) {}
}
