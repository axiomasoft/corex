<?php

declare(strict_types=1);

namespace CoreX\Intl\Contracts;

use CoreX\Intl\Data\LocaleResolution;
use CoreX\Intl\Data\LocalizedText;
use CoreX\Intl\Data\TranslationResult;

interface TranslationReader
{
    public function read(LocalizedText $text, LocaleResolution $locale): TranslationResult;
}
