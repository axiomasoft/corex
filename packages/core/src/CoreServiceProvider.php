<?php

declare(strict_types=1);

namespace CoreX;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Internal\PruneAuditLog;
use CoreX\Audit\Laravel\AuditRegistrar;
use CoreX\Config\Internal\Commands\ValidateConfigCommand;
use CoreX\Config\Laravel\ConfigRegistrar;
use CoreX\Context\Laravel\ContextRegistrar;
use CoreX\Data\Laravel\DataRegistrar;
use CoreX\Departments\Laravel\DepartmentsRegistrar;
use CoreX\Errors\Internal\PackageProblemDefinitions;
use CoreX\Errors\Laravel\ErrorsRegistrar;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Features\Laravel\FeaturesRegistrar;
use CoreX\Messaging\Internal\Commands\PruneOutboxMessages;
use CoreX\Messaging\Internal\Commands\RelayOutbox;
use CoreX\Messaging\Internal\Commands\ShowDeliveryHealth;
use CoreX\Messaging\Internal\PrunePubSubMessages;
use CoreX\Messaging\Laravel\MessagingRegistrar;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Laravel\CoreModulesRegistrar;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Observability\Laravel\ObservabilityRegistrar;
use CoreX\Settings\Laravel\SettingsRegistrar;
use CoreX\Storage\Laravel\StorageRegistrar;
use CoreX\Time\Laravel\TimeRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CoreServiceProvider extends ServiceProvider
{
    /**
     * Shipped schema generation of the sys_* migration set. A consumer
     * publishes a COPY of the migrations, so a package upgrade cannot mutate
     * them in place — schema changes ship as NEW additive migration files under
     * the same publish tag and bump this constant. Lets a host verify which
     * generation its published set is on before re-running vendor:publish.
     */
    public const int SCHEMA_VERSION = 1;

    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [
        ErrorsRegistrar::class,
        ObservabilityRegistrar::class,
        ConfigRegistrar::class,
        ContextRegistrar::class,
        DataRegistrar::class,
        MessagingRegistrar::class,
        SettingsRegistrar::class,
        AuditRegistrar::class,
        FeaturesRegistrar::class,
        DepartmentsRegistrar::class,
        StorageRegistrar::class,
        TimeRegistrar::class,
        CoreModulesRegistrar::class,
    ];

    #[Override]
    public function register(): void
    {
        ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex.php', 'corex');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }

        // After the kernel bindings: module-aware services (settings defaults, registries) refine them.
        $this->app->register(ModulesServiceProvider::class);
    }

    public function boot(): void
    {
        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                PruneAuditLog::class,
                PrunePubSubMessages::class,
                PruneOutboxMessages::class,
                RelayOutbox::class,
                ShowDeliveryHealth::class,
                ValidateConfigCommand::class,
            ]);
        }

        // NO loadMigrationsFrom: every sys_* migration is PG-only
        // DDL living in database/migrations/pg/, and this provider is registered
        // by sqlite-backed consumer suites too — an unconditional load would
        // break them. Consumers get the schema through publish + an explicit,
        // tenant-scoped migrate (see README).
        //
        // Connection-scoped publish: sys_* is TENANT-schema DDL. The
        // `-tenant` tag names that scope so a consumer never runs it on the
        // central/root connection by accident (pre-mortem P1R C5) — the plain
        // `corex-migrations` alias stays for the common single-DB (boxed) case.
        // Under P2 (DB-per-account) the consumer runs
        // `migrate --database=<tenant>` after publishing.
        $this->publishes([
            __DIR__.'/../database/migrations/pg' => database_path('migrations'),
        ], ['corex-migrations', 'corex-migrations-tenant']);

        $this->publishes([
            __DIR__.'/../config/corex.php' => config_path('corex.php'),
        ], 'corex-config');
    }
}
