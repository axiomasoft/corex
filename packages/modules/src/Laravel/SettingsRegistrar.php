<?php

declare(strict_types=1);

namespace CoreX\Modules\Laravel;

use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Settings\CompiledSettingDefaultsProvider;
use CoreX\Support\PackageRegistrar;
use CoreX\Support\RegistersNothingOnBoot;
use Illuminate\Contracts\Foundation\Application;
use Override;

/** @internal Composition registrar. Not package public API. */
final class SettingsRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        // Rebind the boxed Null default (CoreServiceProvider::register(),
        // registered before this provider) to the real manifest-backed
        // implementation — corex/modules is present, so
        // manifest-declared setting defaults now reach the runtime cascade.
        $app->singleton(
            SettingDefaultsProvider::class,
            static fn (Application $app): CompiledSettingDefaultsProvider => new CompiledSettingDefaultsProvider(
                $app->make(ModuleRegistry::class),
            ),
        );
    }
}
