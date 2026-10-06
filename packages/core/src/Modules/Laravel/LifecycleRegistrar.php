<?php

declare(strict_types=1);

namespace CoreX\Modules\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Database\Contracts\SchemaSets;
use CoreX\Database\Enums\SchemaLifecycle;
use CoreX\Database\Enums\SchemaScope;
use CoreX\Database\Exceptions\SchemaSetException;
use CoreX\Modules\Contracts\ModuleLifecycle;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Contracts\ModuleStateReader;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Modules\Contracts\RecordsRegistrar;
use CoreX\Modules\Internal\Discovery\ModuleDiscovery;
use CoreX\Modules\Internal\Lifecycle\DatabaseModuleLifecycle;
use CoreX\Modules\Internal\Lifecycle\DatabaseModuleStateReader;
use CoreX\Modules\Internal\Lifecycle\DatabaseRecordsRegistrar;
use CoreX\Modules\Internal\Support\ModulesOptions;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Override;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class LifecycleRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        // mod_modules/mod_records now exist — read/write them directly;
        // ArrayModuleStateReader remains available for tests that inject a fake.
        // Bound via a closure (NOT a bare class-string): the reader MUST read the
        // module connection (ModulesOptions->connection, null = app
        // default) — the same seam RecordsRegistrar/lifecycle/epoch use. A bare
        // class-string autowires $connection = null and silently reads the ambient
        // default, so with a configured module DB the enabled set comes back empty
        // and every module reads inactive (a defect a targeted audit caught).
        $app->singleton(ModuleStateReader::class, static function (Application $app): DatabaseModuleStateReader {
            return new DatabaseModuleStateReader($app->make(ConnectionResolverInterface::class), $app->make(ModulesOptions::class)->connection);
        });

        // RecordsRegistrar and the lifecycle read/write the module connection
        // (ModulesOptions->connection, null = app default) — the same
        // seam the epoch and state reader use, so auto-claims land in the module
        // DB, not the ambient default.
        $app->singleton(RecordsRegistrar::class, static function (Application $app): DatabaseRecordsRegistrar {
            return new DatabaseRecordsRegistrar($app->make(ConnectionResolverInterface::class), $app->make(ClockInterface::class), $app->make(ModulesOptions::class)->connection);
        });

        // Keep the assembled wiring overridable for isolated lifecycle tests.
        $app->singleton('corex.modules.wiring', fn (): array => $this->moduleWiring($app->make(SchemaSets::class)));

        $app->singleton(ModuleLifecycle::class, static fn (Application $app): DatabaseModuleLifecycle => self::moduleLifecycle($app));
    }

    /**
     * Module versions come from Composer; schema paths come from their owners' registered sets.
     *
     * @return array{codeVersions: array<string, string>, migrationPaths: array<string, string>}
     */
    private function moduleWiring(SchemaSets $sets): array
    {
        $codeVersions = [];
        $migrationPaths = [];
        foreach ($sets->all() as $set) {
            if ($set->scope === SchemaScope::Tenant && $set->lifecycle === SchemaLifecycle::Module) {
                $moduleSet = $sets->moduleSet($set->package);

                if ($moduleSet === null) {
                    throw SchemaSetException::invalidSet($set->id());
                }
                $migrationPaths[$set->package] = $moduleSet->path;
            }
        }

        $path = base_path('vendor/composer/installed.json');

        if (! is_file($path)) {
            return ['codeVersions' => $codeVersions, 'migrationPaths' => $migrationPaths];
        }

        /** @var mixed $data */
        $data = json_decode((string) file_get_contents($path), associative: true);

        if (! is_array($data)) {
            return ['codeVersions' => $codeVersions, 'migrationPaths' => $migrationPaths];
        }

        /** @var list<mixed> $packages */
        $packages = is_array($data['packages'] ?? null) ? $data['packages'] : $data;

        foreach ($packages as $package) {
            if (! is_array($package) || ($package['type'] ?? null) !== 'corex-module') {
                continue;
            }

            $name = $package['name'] ?? null;

            if (! is_string($name)) {
                continue;
            }

            if (is_string($package['version'] ?? null)) {
                $codeVersions[$name] = $package['version'];
            }
        }

        return ['codeVersions' => $codeVersions, 'migrationPaths' => $migrationPaths];
    }

    private static function moduleLifecycle(Application $app): DatabaseModuleLifecycle
    {
        /** @var array{codeVersions: array<string, string>, migrationPaths: array<string, string>} $wiring */
        $wiring = $app->make('corex.modules.wiring');
        $connection = $app->make(ModulesOptions::class)->connection;

        return new DatabaseModuleLifecycle(
            registry: $app->make(ModuleRegistry::class),
            discovery: $app->make(ModuleDiscovery::class),
            records: $app->make(RecordsRegistrar::class),
            container: $app,
            tenantContextResolver: $app->make(TenantContextResolver::class),
            connections: $app->make(ConnectionResolverInterface::class),
            events: $app->make(EventDispatcher::class),
            clock: $app->make(ClockInterface::class),
            codeVersions: $wiring['codeVersions'],
            migrationPaths: $wiring['migrationPaths'],
            connection: $connection,
        );
    }
}
