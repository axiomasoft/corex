<?php

declare(strict_types=1);

namespace CoreX\Settings\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Laravel\ConfiguredImplementation;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Settings\Contracts\SettingDefaultsProvider;
use CoreX\Settings\Contracts\SettingsRepository;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SettingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(SettingDefaultsProvider::class, static fn (Application $app): SettingDefaultsProvider => ConfiguredImplementation::resolve($app, SettingDefaultsProvider::class, $app->make(CoreOptions::class)->settingDefaultsProvider));
        $app->singleton(SettingsRepository::class, static fn (Application $app): SettingsRepository => ConfiguredImplementation::resolve($app, SettingsRepository::class, $app->make(CoreOptions::class)->settingsRepository));
    }

    #[Override]
    public function boot(Application $app): void {}
}
