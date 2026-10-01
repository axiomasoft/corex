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
    /**
     * Shipped schema generation of the mod_* migration set. A consumer
     * publishes a COPY, so upgrades ship as NEW additive migration files under
     * the same publish tag and bump this constant (mirrors
     * {@see CoreServiceProvider::SCHEMA_VERSION}).
     */
    public const int SCHEMA_VERSION = 1;

    #[Override]
    public function register(): void
    {
        $this->app->tag([ModulesConfigSection::class], ConfigReportSection::class);

        $raw = $this->app['config']->get('corex.modules', []);
        $options = ModulesOptions::fromArray(is_array($raw) ? $raw : []);
        $this->app->instance(ModulesOptions::class, $options);

        foreach ($this->registrars() as $registrar) {
            $registrar->register($this->app);
        }
    }

    public function boot(): void
    {
        // NOT loadMigrationsFrom: mod_* is PG-only DDL (CHECK
        // constraints, advisory locks) and this provider is registered by
        // every consumer, including sqlite-backed suites (Crm/Tasks). The
        // pg-lane test/deploy path runs these migrations explicitly,
        // scoped by --path (see tests/Feature/ModuleLifecycleTest.php).
        //
        // Connection-scoped publish: mod_* is PG-only TENANT-schema
        // DDL (advisory locks, CHECK, jsonb). NOT loadMigrationsFrom — see the
        // note above; consumers publish + migrate explicitly on the tenant
        // connection. The `-tenant` tag names that scope (caught in an earlier
        // pre-mortem); the plain `corex-migrations` alias serves the
        // boxed single-DB case.
        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], ['corex-migrations', 'corex-migrations-tenant']);

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
