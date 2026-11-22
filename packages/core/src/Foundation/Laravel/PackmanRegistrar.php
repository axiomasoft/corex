<?php

declare(strict_types=1);

namespace CoreX\Foundation\Laravel;

use Closure;
use CoreX\Audit\Internal\CurrentActor;
use CoreX\Audit\Internal\ModuleAuditHook;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Internal\LocalWorkTransactions;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Foundation\Contracts\LifecyclePlans;
use CoreX\Foundation\Contracts\ModuleReferences;
use CoreX\Foundation\Contracts\ProviderRemovalGate;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Internal\CoreModuleReferences;
use CoreX\Foundation\Internal\DatabaseLifecyclePlans;
use CoreX\Foundation\Internal\DatabaseReferenceAdmission;
use CoreX\Foundation\Internal\GuardedModuleLifecycle;
use CoreX\Foundation\Internal\HostProviderRemoval;
use CoreX\Foundation\Internal\LifecycleCatalogue;
use CoreX\Foundation\Internal\LifecycleFrame;
use CoreX\Foundation\Internal\PackmanActorResolver;
use CoreX\Foundation\Internal\PackmanContextResolver;
use CoreX\Foundation\Internal\PackmanFeatureFlags;
use CoreX\Invocation\Internal\OperationSectionCompiler;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Application as LaravelApplication;
use InvalidArgumentException;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Context\Contracts\ActorResolverInterface;
use Packman\Context\Contracts\ContextResolverInterface;
use Packman\Context\Data\ActorRef;
use Packman\Features\Contracts\FeatureFlagsInterface;
use Packman\Foundation\Concerns\RegistersNothingOnBoot;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Modules\Contracts\Compiler\ManifestSectionCompiler;
use Packman\Modules\Contracts\Entities\EntityRegistryHook;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;

/** CoreX's domain adapters for the Packman module runtime. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PackmanRegistrar implements PackageRegistrar
{
    use RegistersNothingOnBoot;

    #[Override]
    public function register(Application $app): void
    {
        $app->scoped(CoreModuleReferences::class);
        $app->tag(CoreModuleReferences::class, ModuleReferences::class);

        $app->scoped(ReferenceAdmission::class, static fn (Application $app): DatabaseReferenceAdmission => new DatabaseReferenceAdmission(
            current: $app->make(CurrentWorkScope::class), ticketFor: $app->make(LocalWorkTransactions::class)->localTicket(...),
            control: $app->make(DatabaseScopeControl::class), catalogue: $app->make(LifecycleCatalogue::class),
        ));
        $app->when(DatabaseLifecyclePlans::class)->needs(Closure::class)->give(static fn (Application $app): Closure => $app->make(LocalWorkTransactions::class)->commitConfirmation(...));
        $app->when(DatabaseLifecyclePlans::class)->needs('$packmanConnection')->give(static fn (Application $app): ?string => self::packmanConnection($app->make(Repository::class)));
        $app->scoped(DatabaseLifecyclePlans::class);
        $app->alias(DatabaseLifecyclePlans::class, LifecyclePlans::class);
        $app->scoped(ProviderRemovalGate::class, HostProviderRemoval::class);
        $app->scoped(LifecycleFrame::class);

        if ($app instanceof LaravelApplication) {
            $app->beforeResolving($this->decorateLifecycle(...));
        } else {
            $app->extend(ModuleLifecycle::class, static fn (ModuleLifecycle $host): GuardedModuleLifecycle => new GuardedModuleLifecycle(inner: $host, app: $app, origin: $app->make(LifecycleFrame::class), booted: true));
        }

        $app->singleton(ContextResolverInterface::class, PackmanContextResolver::class);
        $app->scoped(ActorResolverInterface::class, static fn (Application $app): PackmanActorResolver => new PackmanActorResolver(
            static fn (): ActorRef => self::actorRef($app->make(CurrentActor::class)),
        ));
        $app->singleton(FeatureFlagsInterface::class, PackmanFeatureFlags::class);
        $app->tag([ModuleAuditHook::class], EntityRegistryHook::class);
        $app->tag([OperationSectionCompiler::class], ManifestSectionCompiler::class);
    }

    /** @var array<string,true> */
    private array $transient = [];

    /** @param array<array-key,mixed> $parameters */
    private function decorateLifecycle(string $binding, array $parameters, LaravelApplication $app): void
    {
        if ($binding !== $app->getAlias(ModuleLifecycle::class) || ! $app->bound($binding)) {
            return;
        }
        $frame = $app->make(LifecycleFrame::class);

        if ($frame->attaching) {
            return;
        }
        $decorate = static fn (ModuleLifecycle $host, LaravelApplication $app): ModuleLifecycle => $host instanceof GuardedModuleLifecycle
            ? $host : new GuardedModuleLifecycle(inner: $host, app: $app, origin: $app->make(LifecycleFrame::class), booted: $app->isBooted());

        if (! $app->isShared($binding)) {
            if (! isset($this->transient[$binding])) {
                $this->transient[$binding] = true;
                $app->extend($binding, $decorate);
            }

            return;
        }
        $frame->attaching = true;

        try {
            if (! $app->make(ModuleLifecycle::class) instanceof GuardedModuleLifecycle) {
                $app->extend($binding, $decorate);
            }
        } finally {
            $frame->attaching = false;
        }
    }

    private static function packmanConnection(Repository $config): ?string
    {
        $section = $config->get('packman.modules', []);
        $name = is_array($section) && array_key_exists('connection', $section)
            ? $section['connection'] : $config->get('modules.connection');

        return $name === null || is_string($name) ? $name : throw new InvalidArgumentException('Invalid Packman owner connection.');
    }

    private static function actorRef(CurrentActor $actor): ActorRef
    {
        return new ActorRef(type: $actor->type()->value, id: $actor->id());
    }
}
