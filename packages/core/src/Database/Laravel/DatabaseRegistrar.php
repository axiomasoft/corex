<?php

declare(strict_types=1);

namespace CoreX\Database\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Contracts\SchemaMigrator;
use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Database\Contracts\SchemaSets;
use CoreX\Database\Exceptions\SchemaSetException;
use CoreX\Database\Internal\Commands\SchemaMigrateCommand;
use CoreX\Database\Internal\Commands\SchemaStatusCommand;
use CoreX\Database\Internal\CoreSchemaSets;
use CoreX\Database\Internal\LaravelSchemaMigrator;
use CoreX\Database\Internal\TaggedSchemaSets;
use CoreX\Modules\Contracts\PackageRegistrar;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DatabaseRegistrar implements PackageRegistrar
{
    public function register(Application $app): void
    {
        $app->tag([CoreSchemaSets::class], SchemaSetProvider::class);
        $app->singleton(SchemaSets::class, static fn (Application $app): SchemaSets => self::schemaSets($app));
        $app->singleton(SchemaMigrator::class, static fn (Application $app): SchemaMigrator => new LaravelSchemaMigrator(sets: $app->make(SchemaSets::class), migrator: $app->make('migrator'), db: $app->make('db')));
    }

    public function boot(Application $app): void
    {
        if ($app->runningInConsole()) {
            Artisan::starting(self::registerCommands(...));
        }
    }

    private static function registerCommands(Artisan $artisan): void
    {
        $artisan->resolveCommands([SchemaMigrateCommand::class, SchemaStatusCommand::class]);
    }

    private static function schemaSets(Application $app): SchemaSets
    {
        $providers = [];
        foreach ($app->tagged(SchemaSetProvider::class) as $provider) {
            if (! $provider instanceof SchemaSetProvider) {
                throw SchemaSetException::invalidSet('SchemaSetProvider tag');
            }
            $providers[] = $provider;
        }

        return new TaggedSchemaSets($providers);
    }
}
