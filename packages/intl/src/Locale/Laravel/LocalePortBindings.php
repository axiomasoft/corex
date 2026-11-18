<?php

declare(strict_types=1);

namespace CoreX\Intl\Locale\Laravel;

use CoreX\Intl\Locale\Contracts\LocaleValidation;
use CoreX\Intl\Locale\Internal\Bcp47Validator;
use Illuminate\Contracts\Foundation\Application;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Registers the owning package's call ports with their private implementations. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class LocalePortBindings
{
    public static function register(Application $app): void
    {
        $app->bind(LocaleValidation::class, static fn (Application $app): LocaleValidation => $app->make(Bcp47Validator::class));
    }
}
