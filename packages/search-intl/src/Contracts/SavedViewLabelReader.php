<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Contracts;

use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Data\TranslationResult;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SavedViewLabelReader
{
    /**
     * @param  array<string, string>  $localizedName
     */
    public function read(array $localizedName, LocaleResolution $locale): TranslationResult;
}
