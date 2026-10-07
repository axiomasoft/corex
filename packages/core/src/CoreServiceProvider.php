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
use CoreX\Database\Laravel\DatabaseRegistrar;
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
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [
        ErrorsRegistrar::class,
        ObservabilityRegistrar::class,
        ConfigRegistrar::class,
        ContextRegistrar::class,
        DataRegistrar::class,
        DatabaseRegistrar::class,
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
        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

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

        $this->publishes([
            __DIR__.'/../config/corex.php' => config_path('corex.php'),
        ], 'corex-config');
    }
}
