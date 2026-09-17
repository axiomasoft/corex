<?php

declare(strict_types=1);

namespace CoreX\IntlSearch\Internal;

use CoreX\Intl\Contracts\TranslationReader;
use CoreX\Intl\Data\LocaleResolution;
use CoreX\Intl\Data\LocalizedText;

final class LocalizedFieldProjector
{
    public function __construct(private readonly TranslationReader $translations) {}

    public function project(LocalizedText $text, LocaleResolution $locale): ?string
    {
        $result = $this->translations->read(text: $text, locale: $locale);

        if (! in_array($result->status, ['found', 'available'], true) || $result->value === null) {
            return null;
        }

        return $result->value;
    }
}
