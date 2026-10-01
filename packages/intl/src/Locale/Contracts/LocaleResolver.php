<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Intl\Locale\Data\LocaleInputs;
use CoreX\Intl\Locale\Data\LocalePolicy;
use CoreX\Intl\Locale\Data\LocaleResolution;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface LocaleResolver
{
    public function resolve(LocaleInputs $inputs, LocalePolicy $policy): LocaleResolution;
}
