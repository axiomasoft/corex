<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Internal;

use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Contracts\TranslationReader;
use CoreX\Intl\Translation\Data\LocalizedText;
use CoreX\Intl\Translation\Data\TranslationResult;
use CoreX\SearchIntl\Contracts\SavedViewLabelReader;
use Override;

final class SqlSavedViewLabelReader implements SavedViewLabelReader
{
    public function __construct(private readonly TranslationReader $translations) {}

    #[Override]
    public function read(array $localizedName, LocaleResolution $locale): TranslationResult
    {
        if ($localizedName === []) {
            return new TranslationResult(status: 'invalid', value: null, usedLocale: null, wasFallback: false);
        }

        return $this->translations->read(
            text: new LocalizedText(declaredField: 'saved_view.name', schemaHash: 'saved-view/1', map: $localizedName),
            locale: $locale,
        );
    }
}
