<?php

declare(strict_types=1);

namespace CoreX\Modules;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Contracts\ConfigReportSection;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Internal\Support\ModulesConfigSection;
use CoreX\Modules\Internal\Support\ModulesOptions;
use CoreX\Modules\Laravel\LifecycleRegistrar;
use CoreX\Modules\Laravel\ModulesBootRegistrar;
use CoreX\Modules\Laravel\RegistryRegistrar;
use CoreX\Modules\Laravel\SettingsRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ModulesServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([ModulesConfigSection::class], ConfigReportSection::class);

        foreach ($this->registrars() as $registrar) {
            $registrar->register($this->app);
        }
    }

    public function boot(): void
    {
        $this->app->make(ModulesOptions::class);

        // Deploy hook: `php artisan optimize` recompiles the registry. No
        // clear command — `optimize:clear` leaves the file, the next
        // `optimize` overwrites it atomically.
        $this->optimizes(optimize: 'corex:modules:compile', key: 'corex.modules');

        foreach ($this->registrars() as $registrar) {
            $registrar->boot($this->app);
        }
    }

    /** @return list<PackageRegistrar> */
    private function registrars(): array
    {
        return [
            new RegistryRegistrar,
            new LifecycleRegistrar,
            new SettingsRegistrar,
            new ModulesBootRegistrar,
        ];
    }
}
