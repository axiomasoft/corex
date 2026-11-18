<?php

declare(strict_types=1);

namespace CoreX\Settings\Laravel;

use CoreX\Config\Data\CoreOptions;
use CoreX\Settings\Contracts\SettingsRepository;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Laravel\ConfiguredImplementation;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Settings\Contracts\SettingDefaultsProviderInterface as SettingDefaultsProvider;

/** Composition of the kernel module; the public provider delegates here. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SettingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->singleton(SettingDefaultsProvider::class, static fn (Application $app): SettingDefaultsProvider => ConfiguredImplementation::resolve(app: $app, contract: SettingDefaultsProvider::class, implementation: $app->make(CoreOptions::class)->settingDefaultsProvider));
        $app->singleton(SettingsRepository::class, static fn (Application $app): SettingsRepository => ConfiguredImplementation::resolve(app: $app, contract: SettingsRepository::class, implementation: $app->make(CoreOptions::class)->settingsRepository));
    }

    #[Override]
    public function boot(Application $app): void {}
}
