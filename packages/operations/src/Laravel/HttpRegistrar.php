<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Operations\Bulk\Laravel\BulkHttpRegistrar;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Errors\Contracts\ErrorMapping;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Laravel\OperationErrorBoundary;
use CoreX\Operations\Internal\Http\HttpOperationResponder;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Internal\TrustedContextResolver;
use CoreX\Operations\Invocation\Laravel\AuthenticatedOperationContext;
use CoreX\Operations\Laravel\Contracts\HttpOperationDispatcher as Dispatcher;
use CoreX\Operations\Laravel\Support\ApiOptions;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;

/** Composition of the operations package. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class HttpRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        $app->bind(HttpOperationDispatcher::class, static fn (Application $app): HttpOperationDispatcher => self::dispatcher(app: $app));
        $app->alias(HttpOperationDispatcher::class, Dispatcher::class);

        if (! $app->bound(ContextResolver::class)) {
            $app->bind(ContextResolver::class, static fn (Application $app): TrustedContextResolver => new TrustedContextResolver(
                tenants: $app->make(TenantContextResolver::class),
                manager: $app->make(TenancyManager::class),
                requirePhysicalTenancy: $app->make(ApiOptions::class)->requirePhysicalTenancy,
            ));
        }
    }

    #[Override]
    public function boot(Application $app): void
    {
        $options = $app->make(ApiOptions::class);

        if ($options->routesEnabled && ! $app->make('router')->has('corex.api.discovery')) {
            (new BulkHttpRegistrar)->boot($app);
            Route::prefix($options->routePrefix)
                ->middleware([OperationErrorBoundary::class, ...$options->routeMiddleware])
                ->group(__DIR__.'/../../routes/api.php');
        }
    }

    private static function dispatcher(Application $app): HttpOperationDispatcher
    {
        $key = $app->make(Repository::class)->get('app.key');

        return new HttpOperationDispatcher(
            operations: $app->make(OperationRegistry::class),
            contexts: $app->make(AuthenticatedOperationContext::class),
            serializer: $app->make(ResponseSerializer::class),
            errors: $app->make(ErrorMapping::class),
            invoker: $app->make(OperationInvoker::class),
            responder: $app->make(HttpOperationResponder::class),
            etagSecret: is_string($key) && $key !== '' ? $key : null,
            container: $app,
        );
    }
}
