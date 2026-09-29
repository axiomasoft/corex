<?php

declare(strict_types=1);

namespace CoreX\Operations;

use CoreX\Config\SecretRef;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Modules\Contracts\ManifestSectionCompiler;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Authorization\Internal\DeclaredFieldAuthorizer;
use CoreX\Operations\Authorization\Internal\DenyClientScopeAuthorizer;
use CoreX\Operations\Authorization\Internal\DenyOperationAuthorizer;
use CoreX\Operations\Authorization\Internal\DenyPrincipalResolver;
use CoreX\Operations\Bulk\Commands\WorkBulkOperations;
use CoreX\Operations\Bulk\Data\BulkOptions;
use CoreX\Operations\Bulk\Laravel\BulkRegistrar;
use CoreX\Operations\Cost\Laravel\CostRegistrar;
use CoreX\Operations\Definition\Contracts\SchemaContributor;
use CoreX\Operations\Definition\Internal\DeclaredSchemaContributor;
use CoreX\Operations\Definition\OperationRegistry;
use CoreX\Operations\Definition\OperationSectionCompiler;
use CoreX\Operations\Errors\ApiError;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Internal\ErrorMapper;
use CoreX\Operations\Errors\Internal\NegotiatingResponseSerializer;
use CoreX\Operations\Idempotency\Contracts\IdempotencyStore;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Idempotency\Internal\IdempotentOperationInvoker;
use CoreX\Operations\Idempotency\Internal\SqlIdempotencyStore;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Contracts\RequestContextProvider;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Invocation\Internal\CurrentRequestContext;
use CoreX\Operations\Invocation\Internal\DefaultOperationInvoker;
use CoreX\Operations\Invocation\Internal\TrustedContextResolver;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Laravel\Commands\PruneApiOperations;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\OpenApi\Commands\WriteApiSnapshots;
use CoreX\Operations\Pagination\Contracts\CursorKeyring;
use CoreX\Operations\Pagination\OrderingRegistry;
use CoreX\Operations\Pagination\SignedCursorCodec;
use CoreX\Time\SystemClock;
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

        $this->mergeConfigFrom(__DIR__.'/../config/corex-operations.php', 'corex-operations');
        $this->app->bind(ApiOptions::class, static function (Application $app): ApiOptions {
            $config = $app['config']->get('corex-operations');

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
                leaseSeconds: (int) $app['config']->get('corex-operations.idempotency.lease_seconds', 30),
                retentionSeconds: (int) $app['config']->get('corex-operations.idempotency.retention_seconds', 86_400),
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
        ], 'corex-operations-migrations-tenant');
        $this->publishes([
            __DIR__.'/../database/migrations/budget' => database_path('migrations/budget'),
        ], 'corex-operations-migrations-budget');
        $this->publishes([
            __DIR__.'/../database/migrations/tenant' => database_path('migrations/tenant'),
        ], 'corex-operations-migrations-tenant');

        $this->publishes([__DIR__.'/../config/corex-operations.php' => config_path('corex-operations.php')], 'corex-operations-config');
    }
}
