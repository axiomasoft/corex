<?php

declare(strict_types=1);

namespace CoreX\Media\Conversions\Contracts;

use CoreX\Media\Conversions\Data\ConversionStatus;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaConversionRef;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
interface ConversionOperations
{
    public function inspect(MediaContext $context, MediaConversionRef $ref): ConversionStatus;
}
