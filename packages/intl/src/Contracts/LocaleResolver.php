<?php

declare(strict_types=1);

namespace CoreX\Intl\Contracts;

use CoreX\Intl\Data\LocaleInputs;
use CoreX\Intl\Data\LocalePolicy;
use CoreX\Intl\Data\LocaleResolution;

interface LocaleResolver
{
    public function resolve(LocaleInputs $inputs, LocalePolicy $policy): LocaleResolution;
}
