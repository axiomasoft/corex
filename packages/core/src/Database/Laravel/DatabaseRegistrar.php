<?php

declare(strict_types=1);

namespace CoreX\Database\Laravel;

use Closure;
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
use CoreX\Modules\Internal\Lifecycle\DatabaseRecordsRegistrar;
use CoreX\Modules\Internal\Lifecycle\ModuleMigrationClaims;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Events\Dispatcher;
use LogicException;
use Psr\Clock\ClockInterface;
use ReflectionClass;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DatabaseRegistrar implements PackageRegistrar
{
    public function register(Application $app): void
    {
        $app->tag([CoreSchemaSets::class], SchemaSetProvider::class);
        $app->singleton(SchemaSets::class, static fn (Application $app): SchemaSets => self::schemaSets($app));
        $app->singleton(SchemaMigrator::class, static fn (Application $app): SchemaMigrator => new LaravelSchemaMigrator(sets: $app->make(SchemaSets::class), migrator: $app->make('migrator'), db: $app->make('db'), moduleMigrations: static fn (Connection $db, string $name, array $owners, Closure $run) => self::runModuleMigrations($app, $db, $name, $owners, $run)));
    }

    public function boot(Application $app): void
    {
        if ($app->runningInConsole()) {
            Artisan::starting(self::registerCommands(...));
        }
    }

    /** @param array<string, string> $owners */
    private static function runModuleMigrations(Application $app, Connection $db, string $name, array $owners, Closure $run): void
    {
        $events = $db->getEventDispatcher();

        if (! $events instanceof Dispatcher) {
            throw new LogicException('Module schema ownership requires the Laravel migration dispatcher.');
        }
        $db->transaction(static function () use ($db, $name, $events, $owners, $run, $app): void {
            $db->statement('select pg_advisory_xact_lock(hashtext(?))', ['corex:module-lifecycle-graph']);
            $claims = new ModuleMigrationClaims($db, new DatabaseRecordsRegistrar($app->make('db'), $app->make(ClockInterface::class), $name), $events);
            $error = $claims->run(
                moduleForMigration: static function (Migration $migration) use ($owners): ?string {
                    $file = new ReflectionClass($migration)->getFileName();

                    if ($file === false) {
                        return null;
                    }
                    $canonical = realpath($file);

                    return $owners[$canonical === false ? $file : $canonical] ?? null;
                },
                run: $run,
            );

            if ($error !== null) {
                throw $error;
            }
        });
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
