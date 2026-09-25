<?php

declare(strict_types=1);

namespace CoreX\Api;

use CoreX\Api\Contracts\ContextResolver;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\OperationInvoker;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\RequestContextProvider;
use CoreX\Api\Contracts\ResponseSerializer;
use CoreX\Api\Contracts\SchemaContributor;
use CoreX\Api\Internal\CurrentRequestContext;
use CoreX\Api\Internal\DeclaredSchemaContributor;
use CoreX\Api\Internal\DefaultOperationInvoker;
use CoreX\Api\Internal\DenyOperationAuthorizer;
use CoreX\Api\Internal\DenyPrincipalResolver;
use CoreX\Api\Internal\EnvelopeSerializer;
use CoreX\Api\Internal\TrustedContextResolver;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Override;

/** Manually registered, with no discovery entry, routes or migrations. */
final class ApiServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-api.php', 'corex-api');
        $this->app->bindIf(PrincipalResolver::class, DenyPrincipalResolver::class);
        $this->app->bindIf(RequestContextProvider::class, CurrentRequestContext::class);
        $this->app->bindIf(OperationAuthorizer::class, DenyOperationAuthorizer::class);
        $this->app->bindIf(OperationInvoker::class, DefaultOperationInvoker::class);
        $this->app->bindIf(ResponseSerializer::class, EnvelopeSerializer::class);
        $this->app->bindIf(SchemaContributor::class, DeclaredSchemaContributor::class);

        if (! $this->app->bound(ContextResolver::class)) {
            $this->app->bind(ContextResolver::class, static fn (Application $app): TrustedContextResolver => new TrustedContextResolver(
                tenants: $app->make(TenantContextResolver::class),
                manager: $app->make(TenancyManager::class),
                requirePhysicalTenancy: (bool) $app['config']->get('corex-api.require_physical_tenancy', true),
            ));
        }
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/corex-api.php' => config_path('corex-api.php')], 'corex-api-config');
    }
}
