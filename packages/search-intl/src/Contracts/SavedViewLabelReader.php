<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Data\TranslationResult;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SavedViewLabelReader
{
    /**
     * @param  array<string, string>  $localizedName
     */
    public function read(array $localizedName, LocaleResolution $locale): TranslationResult;
}
