<?php

declare(strict_types=1);

namespace CoreX\Api;

use CoreX\Api\Commands\PruneApiOperations;
use CoreX\Api\Commands\WorkBulkOperations;
use CoreX\Api\Commands\WriteApiSnapshots;
use CoreX\Api\Contracts\ClientScopeAuthorizer;
use CoreX\Api\Contracts\ContextResolver;
use CoreX\Api\Contracts\CursorKeyring;
use CoreX\Api\Contracts\IdempotencyStore;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationFieldAuthorizer;
use CoreX\Api\Contracts\OperationInvoker;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\ReplayAuthorizer;
use CoreX\Api\Contracts\RequestContextProvider;
use CoreX\Api\Contracts\ResponseSerializer;
use CoreX\Api\Contracts\SchemaContributor;
use CoreX\Api\Data\BulkOptions;
use CoreX\Api\Data\OperationInvocation;
use CoreX\Api\Data\StoredOperationOutcome;
use CoreX\Api\Internal\CurrentRequestContext;
use CoreX\Api\Internal\DeclaredFieldAuthorizer;
use CoreX\Api\Internal\DeclaredSchemaContributor;
use CoreX\Api\Internal\DefaultOperationInvoker;
use CoreX\Api\Internal\DenyClientScopeAuthorizer;
use CoreX\Api\Internal\DenyOperationAuthorizer;
use CoreX\Api\Internal\DenyPrincipalResolver;
use CoreX\Api\Internal\ErrorMapper;
use CoreX\Api\Internal\IdempotentOperationInvoker;
use CoreX\Api\Internal\NegotiatingResponseSerializer;
use CoreX\Api\Internal\OrderingRegistry;
use CoreX\Api\Internal\SignedCursorCodec;
use CoreX\Api\Internal\SqlIdempotencyStore;
use CoreX\Api\Internal\TrustedContextResolver;
use CoreX\Api\Laravel\ApiSchemaRegistrar;
use CoreX\Api\Laravel\BulkRegistrar;
use CoreX\Api\Laravel\CostRegistrar;
use CoreX\Api\Support\ApiOptions;
use CoreX\Modules\Contracts\ManifestSectionCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Support\SecretRef;
use CoreX\Support\Time\SystemClock;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Override;
use Psr\Clock\ClockInterface;

/** Manually registered; routes and tenant migration publication remain explicit. */
final class ApiServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([OperationSectionCompiler::class], ManifestSectionCompiler::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-api.php', 'corex-api');
        $this->app->bind(ApiOptions::class, static function (Application $app): ApiOptions {
            $config = $app['config']->get('corex-api');

            return ApiOptions::fromArray(is_array($config) ? $config : []);
        });
        $this->app->bindIf(PrincipalResolver::class, DenyPrincipalResolver::class);
        $this->app->bindIf(RequestContextProvider::class, CurrentRequestContext::class);
        $this->app->bindIf(ClientScopeAuthorizer::class, DenyClientScopeAuthorizer::class);
        $this->app->bindIf(OperationAuthorizer::class, DenyOperationAuthorizer::class);
        $this->app->bindIf(OperationFieldAuthorizer::class, DeclaredFieldAuthorizer::class);
        $this->app->bindIf(OperationInvoker::class, DefaultOperationInvoker::class);
        $this->app->bindIf(ReplayAuthorizer::class, static fn (Application $app): ReplayAuthorizer => new class($app->make(ModuleRegistry::class), $app->make(ClientScopeAuthorizer::class), $app->make(OperationAuthorizer::class), $app->make(OperationFieldAuthorizer::class)) implements ReplayAuthorizer
        {
            public function __construct(
                private readonly ModuleRegistry $modules,
                private readonly ClientScopeAuthorizer $clientScopes,
                private readonly OperationAuthorizer $authorizer,
                private readonly OperationFieldAuthorizer $fields,
            ) {}

            #[Override]
            public function authorize(
                OperationInvocation $invocation,
                StoredOperationOutcome $outcome,
                bool $replayed,
            ): StoredOperationOutcome {
                if ($replayed && ! $this->modules->isActive($invocation->operation->module, $invocation->context->tenant())) {
                    throw new ApiError('module_unavailable', 404, 'Operation not found.');
                }

                if ($replayed && ! $this->clientScopes->allows($invocation->operation, $invocation->context)) {
                    throw new ApiError('scope_required', 403, 'The client lacks a required operation scope.');
                }

                if ($replayed && ! $this->authorizer->allows($invocation->operation, $invocation->context)) {
                    throw new ApiError('access_denied', 403, 'Operation is not permitted.');
                }

                if ($replayed && $outcome->kind === 'success' && $outcome->data !== null) {
                    return StoredOperationOutcome::success(DeclaredFieldAuthorizer::prune(
                        operation: $invocation->operation,
                        context: $invocation->context,
                        authorizer: $this->fields,
                        data: $outcome->data,
                    ));
                }

                return $outcome;
            }
        });
        $this->app->extend(OperationInvoker::class, static fn (OperationInvoker $inner, Application $app): OperationInvoker => $inner instanceof IdempotentOperationInvoker
            ? $inner
            : new IdempotentOperationInvoker(
                inner: $inner,
                store: static fn (): IdempotencyStore => $app->make(IdempotencyStore::class),
                connection: $app->make(Connection::class),
                modules: $app->make(ModuleRegistry::class),
                authorizer: $app->make(OperationAuthorizer::class),
                replayAuthorizer: $app->make(ReplayAuthorizer::class),
                errors: $app->make(ErrorMapper::class),
            ));
        $this->app->bindIf(ResponseSerializer::class, NegotiatingResponseSerializer::class);
        $this->app->bindIf(SchemaContributor::class, DeclaredSchemaContributor::class);

        if (! $this->app->bound(ClockInterface::class)) {
            $this->app->singleton(ClockInterface::class, SystemClock::class);
        }

        $this->app->singleton(CursorKeyring::class, static function (Application $app): CursorKeyring {
            $options = $app->make(ApiOptions::class);

            return new class($options) implements CursorKeyring
            {
                public function __construct(private readonly ApiOptions $options) {}

                #[Override]
                public function currentKeyId(): string
                {
                    return $this->options->cursorKeyId;
                }

                #[Override]
                public function ttlSeconds(): int
                {
                    return $this->options->cursorTtlSeconds;
                }

                #[Override]
                public function verifyingSecret(string $keyId): ?string
                {
                    $secret = $this->options->cursorKeyring[$keyId] ?? null;

                    if ($secret === null || ! $secret->isPresent()) {
                        return null;
                    }

                    return $secret->reveal();
                }
            };
        });
        $this->app->singleton(SignedCursorCodec::class, static fn (Application $app): SignedCursorCodec => new SignedCursorCodec(
            clock: $app->make(ClockInterface::class),
            keyring: $app->make(CursorKeyring::class),
        ));
        $this->app->scoped(OrderingRegistry::class, static function (Application $app): OrderingRegistry {
            $options = $app->make(ApiOptions::class);

            return OrderingRegistry::fromOperations(
                (new OperationRegistry($app->make(ModuleRegistry::class)))->all($options->defaultMajor),
            );
        });

        $this->app->scoped(IdempotencyStore::class, static function (Application $app): SqlIdempotencyStore {
            $secret = $app['config']->get('app.key');

            if (! is_string($secret) || $secret === '') {
                throw new InvalidArgumentException('APP_KEY must be configured before resolving the idempotency store.');
            }

            return new SqlIdempotencyStore(
                connection: $app->make(Connection::class),
                contexts: $app->make(TenantContextResolver::class),
                clock: $app->make(ClockInterface::class),
                keyHmacSecret: new SecretRef($secret),
                leaseSeconds: (int) $app['config']->get('corex-api.idempotency.lease_seconds', 30),
                retentionSeconds: (int) $app['config']->get('corex-api.idempotency.retention_seconds', 86_400),
            );
        });

        if (! $this->app->bound(ContextResolver::class)) {
            $this->app->bind(ContextResolver::class, static fn (Application $app): TrustedContextResolver => new TrustedContextResolver(
                tenants: $app->make(TenantContextResolver::class),
                manager: $app->make(TenancyManager::class),
                requirePhysicalTenancy: $app->make(ApiOptions::class)->requirePhysicalTenancy,
            ));
        }

        ApiSchemaRegistrar::register($this->app);
        CostRegistrar::register($this->app);
        BulkRegistrar::register($this->app);
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

        $options = $this->app->make(ApiOptions::class);

        if ($options->routesEnabled && ! $this->app['router']->has('corex.api.discovery')) {
            BulkRegistrar::boot($this->app);
            Route::prefix($options->routePrefix)
                ->middleware($options->routeMiddleware)
                ->group(__DIR__.'/../routes/api.php');
        }

        $this->publishes([
            __DIR__.'/../database/migrations/create_sys_api_idempotency_table.php' => database_path('migrations/tenant/create_sys_api_idempotency_table.php'),
        ], 'corex-api-migrations-tenant');
        $this->publishes([
            __DIR__.'/../database/migrations/budget' => database_path('migrations/budget'),
        ], 'corex-api-budget-migrations');
        $this->publishes([
            __DIR__.'/../database/migrations/tenant' => database_path('migrations/tenant'),
        ], 'corex-api-tenant-migrations');

        $this->publishes([__DIR__.'/../config/corex-api.php' => config_path('corex-api.php')], 'corex-api-config');
    }
}
