<?php

declare(strict_types=1);

namespace CoreX\Media\Contracts;

use CoreX\Media\Data\ConversionInput;
use CoreX\Media\Data\ConversionOutput;
use CoreX\Media\Data\ConversionProfile;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface ConversionDriver
{
    public function profile(): ConversionProfile;

    public function convert(ConversionInput $input): ConversionOutput;
}
