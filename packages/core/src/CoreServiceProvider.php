<?php

declare(strict_types=1);

namespace CoreX;

use CoreX\Audit\Laravel\AuditRegistrar;
use CoreX\Config\Laravel\ConfigRegistrar;
use CoreX\Context\Laravel\ContextRegistrar;
use CoreX\Data\Laravel\DataRegistrar;
use CoreX\Database\Laravel\DatabaseRegistrar;
use CoreX\Departments\Laravel\DepartmentsRegistrar;
use CoreX\Errors\Internal\PackageProblemDefinitions;
use CoreX\Features\Laravel\FeaturesRegistrar;
use CoreX\Foundation\Laravel\ConsoleRegistrar;
use CoreX\Foundation\Laravel\PackmanRegistrar;
use CoreX\Messaging\Laravel\MessagingRegistrar;
use CoreX\Observability\Laravel\ObservabilityRegistrar;
use CoreX\Settings\Laravel\SettingsRegistrar;
use CoreX\Storage\Laravel\StorageRegistrar;
use CoreX\Time\Laravel\TimeRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Laravel\ProblemDefinitions;
use Packman\Foundation\Concerns\RegistersPackageRegistrars;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\ModulesServiceProvider;
use Packman\PackmanServiceProvider;
use Packman\Schema\Laravel\SchemaServiceProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class CoreServiceProvider extends ServiceProvider
{
    use RegistersPackageRegistrars;

    /** @var list<class-string<PackageRegistrar>> */
    protected const array REGISTRARS = [
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
        PackmanRegistrar::class,
        ConsoleRegistrar::class,
    ];

    #[Override]
    public function register(): void
    {
        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex.php', 'corex');

        $this->registerPackageRegistrars();

        $this->app->register(PackmanServiceProvider::class);
        $this->app->register(SchemaServiceProvider::class);

        $this->app->register(ModulesServiceProvider::class);
    }

    public function boot(): void
    {
        $this->bootPackageRegistrars();

        $this->publishes([
            __DIR__.'/../config/corex.php' => config_path('corex.php'),
        ], 'corex-config');
    }
}
