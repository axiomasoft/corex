<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Contracts;

use CoreX\Intl\Locale\Data\LocaleInputs;
use CoreX\Intl\Locale\Data\LocalePolicy;
use CoreX\Intl\Locale\Data\LocaleResolution;

interface LocaleResolver
{
    public function resolve(LocaleInputs $inputs, LocalePolicy $policy): LocaleResolution;
}
