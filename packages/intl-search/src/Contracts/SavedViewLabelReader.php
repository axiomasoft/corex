<?php

declare(strict_types=1);

namespace CoreX\IntlSearch\Contracts;

use CoreX\Intl\Data\LocaleResolution;
use CoreX\Intl\Data\TranslationResult;

interface SavedViewLabelReader
{
    /**
     * @param  array<string, string>  $localizedName
     */
    public function read(array $localizedName, LocaleResolution $locale): TranslationResult;
}
