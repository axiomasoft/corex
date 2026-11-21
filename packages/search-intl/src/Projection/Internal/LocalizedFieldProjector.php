<?php

declare(strict_types=1);

namespace CoreX\SearchIntl\Projection\Internal;

use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Contracts\TranslationReader;
use CoreX\Intl\Translation\Data\LocalizedText;
use Packman\Attributes\Internal;

#[Internal]
final readonly class LocalizedFieldProjector
{
    public function __construct(private TranslationReader $translations) {}

    public function project(LocalizedText $text, LocaleResolution $locale): ?string
    {
        $result = $this->translations->read(text: $text, locale: $locale);

        if (! in_array($result->status, ['found', 'available'], true) || $result->value === null) {
            return null;
        }

        return $result->value;
    }
}
