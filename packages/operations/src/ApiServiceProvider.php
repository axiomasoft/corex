<?php

declare(strict_types=1);

namespace CoreX\Operations;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Operations\Bulk\Data\BulkOptions;
use CoreX\Operations\Bulk\Internal\Commands\WorkBulkOperations;
use CoreX\Operations\Bulk\Laravel\BulkBindingsRegistrar;
use CoreX\Operations\Cost\Laravel\CostBindingsRegistrar;
use CoreX\Operations\Errors\Internal\PackageProblemDefinitions;
use CoreX\Operations\Internal\OperationsSchemaSets;
use CoreX\Operations\Laravel\ApiSchemaBindingsRegistrar;
use CoreX\Operations\Laravel\Commands\PruneApiOperations;
use CoreX\Operations\Laravel\HttpRegistrar;
use CoreX\Operations\Laravel\IdempotencyRegistrar;
use CoreX\Operations\Laravel\OperationsBindingsRegistrar;
use CoreX\Operations\Laravel\PaginationRegistrar;
use CoreX\Operations\OpenApi\Internal\Commands\WriteApiSnapshots;
use Illuminate\Support\ServiceProvider;
use Override;

/** Manually registered; routes and tenant migration publication remain explicit. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ApiServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [
        OperationsBindingsRegistrar::class,
        IdempotencyRegistrar::class,
        PaginationRegistrar::class,
        HttpRegistrar::class,
        ApiSchemaBindingsRegistrar::class,
        CostBindingsRegistrar::class,
        BulkBindingsRegistrar::class,
    ];

    #[Override]
    public function register(): void
    {
        $this->app->tag([OperationsSchemaSets::class], SchemaSetProvider::class);

        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-operations.php', 'corex-operations');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $commands = [WriteApiSnapshots::class, PruneApiOperations::class];

            if ($this->app->make(BulkOptions::class)->enabled) {
                $commands[] = WorkBulkOperations::class;
            }

            $this->commands($commands);
        }

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }

        $this->publishes([__DIR__.'/../config/corex-operations.php' => config_path('corex-operations.php')], 'corex-operations-config');
    }
}
