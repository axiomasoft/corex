<?php

declare(strict_types=1);

namespace CoreX\Operations\Laravel;

use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Authorization\Contracts\ReplayAuthorizer;
use CoreX\Operations\Authorization\Internal\DenyClientScopeAuthorizer;
use CoreX\Operations\Authorization\Internal\DenyOperationAuthorizer;
use CoreX\Operations\Authorization\Internal\DenyPrincipalResolver;
use CoreX\Operations\Authorization\Laravel\DeclaredFieldAuthorizer;
use CoreX\Operations\Cost\Data\CostOptions;
use CoreX\Operations\Definition\Contracts\SchemaContributor;
use CoreX\Operations\Definition\Internal\DeclaredSchemaContributor;
use CoreX\Operations\Definition\Laravel\OperationPortBindings;
use CoreX\Operations\Errors\Contracts\ResponseSerializer;
use CoreX\Operations\Errors\Internal\HttpResponseSerializer;
use CoreX\Operations\Idempotency\Internal\DefaultReplayAuthorizer;
use CoreX\Operations\Internal\AppKeyCheck;
use CoreX\Operations\Invocation\Contracts\OperationInvoker;
use CoreX\Operations\Invocation\Contracts\RequestContextProvider;
use CoreX\Operations\Invocation\Internal\CurrentRequestContext;
use CoreX\Operations\Invocation\Internal\DefaultOperationInvoker;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Laravel\Support\OperationsConfigSection;
use CoreX\Time\Laravel\SystemClock;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Config\Contracts\ConfigReportSectionInterface;
use Packman\Config\Contracts\RuntimeConfigCheck;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Psr\Clock\ClockInterface;

/** Composition of the operations package. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class OperationsBindingsRegistrar implements PackageRegistrar
{
    #[Override]
    public function register(Application $app): void
    {
        OperationPortBindings::register($app);
        $app->tag([OperationsConfigSection::class], ConfigReportSectionInterface::class);

        $app->singleton(ApiOptions::class, static fn (Application $app): ApiOptions => ApiOptions::fromArray(
            (array) $app->make(Repository::class)->get('corex-operations', []),
        ));
        $app->bind(AppKeyCheck::class, static fn (Application $app): AppKeyCheck => new AppKeyCheck($app->make(Repository::class)->get('app.key')));
        $app->tag([AppKeyCheck::class], RuntimeConfigCheck::class);
        $app->bindIf(PrincipalResolver::class, DenyPrincipalResolver::class);
        $app->bindIf(RequestContextProvider::class, CurrentRequestContext::class);
        $app->bindIf(ClientScopeAuthorizer::class, DenyClientScopeAuthorizer::class);
        $app->bindIf(OperationAuthorizer::class, DenyOperationAuthorizer::class);
        $app->bindIf(OperationFieldAuthorizer::class, DeclaredFieldAuthorizer::class);
        $app->bindIf(OperationInvoker::class, DefaultOperationInvoker::class);
        $app->bindIf(ReplayAuthorizer::class, static fn (Application $app): ReplayAuthorizer => new DefaultReplayAuthorizer(
            modules: $app->make(ModuleRegistry::class),
            clientScopes: $app->make(ClientScopeAuthorizer::class),
            authorizer: $app->make(OperationAuthorizer::class),
            fields: $app->make(OperationFieldAuthorizer::class),
        ));
        $app->bindIf(ResponseSerializer::class, HttpResponseSerializer::class);
        $app->bindIf(SchemaContributor::class, DeclaredSchemaContributor::class);

        if (! $app->bound(ClockInterface::class)) {
            $app->singleton(ClockInterface::class, SystemClock::class);
        }
    }

    #[Override]
    public function boot(Application $app): void
    {
        $app->make(ApiOptions::class);
        $app->make(CostOptions::class);
    }
}
