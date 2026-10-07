<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use CoreX\Tenancy\Runtime\Internal\FrameworkConfig;
use CoreX\Tenancy\Runtime\Internal\TenantFacilitiesValidator;
use CoreX\Tenancy\Workspace\Internal\Models\Workspace;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Psr\Log\LoggerInterface;

/**
 * Builds the tenant runtime pieces whose construction is more than one expression, so the registrar
 * keeps single-return factory closures. Nothing here captures an application instance that a
 * resident worker could outlive: the hooks resolve the container when they run.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TenancyRuntimeFactory
{
    /**
     * The facility configuration (cache/session drivers and stores) is checked before the first
     * switch, when the manager is first resolved. Bootstrappers are built eagerly and never depend
     * on the manager, so the graph stays acyclic.
     */
    public static function manager(Application $app): DatabaseTenancyManager
    {
        $options = $app->make(TenancyOptions::class);
        new TenantFacilitiesValidator(config: $app->make(FrameworkConfig::class), options: $options)->assertValid();

        $bootstrappers = [];

        foreach ($options->bootstrappers as $class) {
            /** @var TenantBootstrapper $bootstrapper */
            $bootstrapper = $app->make($class);
            $bootstrappers[] = $bootstrapper;
        }

        return new DatabaseTenancyManager(
            bootstrappers: $bootstrappers,
            accounts: $app->make(AccountRead::class),
            connections: $app->make(AccountConnectionResolver::class),
            container: $app,
            events: $app->make(Dispatcher::class),
            centralConnection: $options->centralConnection,
            logger: $app->make(LoggerInterface::class),
        );
    }

    /** The framework's runtime configuration, resolved from the container on every access. */
    public static function frameworkConfig(): FrameworkConfig
    {
        return new FrameworkConfig(static fn (): Repository => Container::getInstance()->make('config'));
    }

    /**
     * The workspace of a queued job, looked up in the account database after it was entered.
     *
     * @return Closure(string): ?WorkspaceRef
     */
    public static function workspaceResolver(): Closure
    {
        return static function (string $id): ?WorkspaceRef {
            $row = Workspace::query()->find($id);

            return $row === null ? null : new WorkspaceRef(
                id: $row->id,
                slug: $row->slug,
                type: $row->type,
                path: $row->path,
                parentId: $row->parent_id,
            );
        };
    }

    /**
     * Payload hook of every queued job: stamps the active account and workspace.
     *
     * @return Closure(string, ?string, array<array-key, mixed>): array<string, mixed>
     */
    public static function payloadHook(): Closure
    {
        return static function (string $connection, ?string $queue, array $payload): array {
            $context = Container::getInstance()->make(TenancyManager::class)->context();

            return $context === null ? [] : ['corex_tenant' => [
                'account' => $context->account->id,
                'workspace' => $context->workspace?->id,
            ]];
        };
    }
}
