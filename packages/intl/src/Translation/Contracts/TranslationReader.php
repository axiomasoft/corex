<?php

declare(strict_types=1);

namespace CoreX\Intl\Translation\Contracts;

use CoreX\Intl\Locale\Data\LocaleResolution;
use CoreX\Intl\Translation\Data\LocalizedText;
use CoreX\Intl\Translation\Data\TranslationResult;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface TranslationReader
{
    public function read(LocalizedText $text, LocaleResolution $locale): TranslationResult;
}
