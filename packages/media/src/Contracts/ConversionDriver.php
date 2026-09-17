<?php

declare(strict_types=1);

namespace CoreX\Media\Contracts;

use CoreX\Media\Data\ConversionInput;
use CoreX\Media\Data\ConversionOutput;
use CoreX\Media\Data\ConversionProfile;

interface ConversionDriver
{
    public function profile(): ConversionProfile;

    public function convert(ConversionInput $input): ConversionOutput;
}
