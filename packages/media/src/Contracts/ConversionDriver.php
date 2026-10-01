<?php

declare(strict_types=1);

namespace CoreX\Media\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Media\Data\ConversionInput;
use CoreX\Media\Data\ConversionOutput;
use CoreX\Media\Data\ConversionProfile;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ConversionDriver
{
    public function profile(): ConversionProfile;

    public function convert(ConversionInput $input): ConversionOutput;
}
