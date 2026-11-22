<?php

declare(strict_types=1);

namespace CoreX\Foundation\Laravel;

use Composer\InstalledVersions;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Data\Data\DataScope;
use CoreX\Features\Data\FeatureFlagDefinition;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Contracts\ReferenceOwners;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use CoreX\Foundation\Internal\LifecycleCatalogue;
use CoreX\Foundation\Internal\LifecycleFrame;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\ApplicationLocator;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class ReferenceWrites
{
    public static function assertCleanup(DataScope $scope, CleanupAction $action): void
    {
        $app = ApplicationLocator::current();
        $app->make(CurrentWorkScope::class)->assert($scope);

        if ($app->make(LifecycleFrame::class)->cleanup !== [$scope, $action]) {
            throw new LifecycleBlocked('Owner cleanup requires an active confirmed plan.');
        }
    }

    public static function cleanupLease(DataScope $scope, CleanupAction $action, string $owner): OwnerLease
    {
        self::assertCleanup(scope: $scope, action: $action);

        return ApplicationLocator::current()->make(LifecycleFrame::class)->leases[$owner]
            ?? throw new LifecycleBlocked('Confirmed plan lacks the required external owner lease.');
    }

    public static function current(): DataScope
    {
        $app = ApplicationLocator::current();

        return $app->make(DataScopeFactory::class)->forCurrentPrincipal($app->make(TenantContextResolver::class)->current());
    }

    /** @param array<string,mixed> $witness */
    public static function enlist(ConnectionInterface $connection, DataScope $scope, string $producer, string $kind, array $witness = []): void
    {
        $refs = self::targets(scope: $scope, producer: $producer, kind: $kind, witness: $witness);
        ApplicationLocator::current()->make(ReferenceAdmission::class)->enlist($scope, $connection, new ModuleRef($producer), ...$refs);
    }

    public static function enlistFeatureDefault(ConnectionInterface $db, FeatureFlagDefinition $def): void
    {
        $scope = ReferenceWrites::current();
        $producer = new ModuleRef('axiomasoft/corex');
        $refs = ReferenceWrites::targets(scope: $scope, producer: $producer->package, kind: 'feature-default', witness: ['package' => $def->module, 'payload' => $def->payload]);
        $app = ApplicationLocator::current();
        $admission = $app->make(ReferenceAdmission::class);
        $native = $app->make(LifecycleFrame::class)->admitManifestDefault;
        $token = $native === null ? null : $native($def);

        if ($def->module !== null && str_contains($def->module, '/')) {
            $refs[] = new ModuleRef($def->module);
        }

        if ($token !== null) {
            // The coordinator owns this exact declared default through its
            // commit. Never grant its closed module to the ordinary writer ticket.
            $refs = array_values(array_filter($refs, static fn (ModuleRef $ref): bool => $ref->package !== $def->module));
        }
        $admission->enlist($scope, $db, $producer, ...$refs);
    }

    public static function reserveEffect(DataScope $scope, ProviderAttempt $attempt, string $producer, ProviderEffectLedger $ledger): AdmissionTicket
    {
        $ticket = self::reserve(scope: $scope, attempt: $attempt, producer: $producer, kind: 'provider-send', witness: ['provider' => $attempt->effect->providerNamespace, 'owner_ref' => $attempt->ownerRef, 'effect_key' => $attempt->effect->effectKey]);
        $prior = $ledger->inspect($attempt->effect);

        if ($prior === null || $prior->ticket->id !== $ticket->id || $prior->ownerRef !== $attempt->ownerRef
            || $prior->claimToken !== $attempt->claimToken || ! hash_equals($prior->digest, $attempt->digest)) {
            throw new LifecycleBlocked('The provider ledger does not own the admitted effect.');
        }

        return $ticket;

    }

    /** @param array<string,mixed> $witness */
    public static function reserve(DataScope $scope, ProviderAttempt $attempt, string $producer, string $kind, array $witness): AdmissionTicket
    {
        $refs = self::targets(scope: $scope, producer: $producer, kind: $kind, witness: $witness);

        return ApplicationLocator::current()->make(ReferenceAdmission::class)->reserve($scope, $attempt, new ModuleRef($producer), ...$refs);
    }

    /**
     * @param  array<string,mixed>  $witness
     * @return list<ModuleRef>
     */
    public static function targets(DataScope $scope, string $producer, string $kind, array $witness): array
    {
        $app = ApplicationLocator::current();

        if ($app->bound(ReferenceOwners::class)) {
            return $app->make(ReferenceOwners::class)->resolve($scope, $producer, $kind, $witness);
        }

        if ($witness === []) {
            return [];
        }
        $refs = [];
        foreach ($witness as $key => $value) {
            if ($value === null) {
                continue;
            }

            if ($key === 'package' && is_string($value) && str_contains($value, '/')) {
                $refs[] = new ModuleRef($value);

                continue;
            }

            if ($key === 'entity' && is_string($value)) {
                $registry = $app->make(ModuleRegistry::class);
                $entity = self::entityOwner(registry: $registry, handle: $value);

                if ($entity !== null) {
                    $refs[] = new ModuleRef($entity);

                    continue;
                }
            }
            // Unknown contribution provenance conservatively intersects every installed
            // package on ordinary writes. Removal requires the actual owner's witness.
            $packages = InstalledVersions::getInstalledPackages();

            if (is_file($app->basePath('vendor/composer/installed.json'))) {
                array_push($packages, ...array_keys($app->make(LifecycleCatalogue::class)->composition()));
            }

            return array_map(static fn (string $name): ModuleRef => new ModuleRef($name), array_values(array_unique($packages)));
        }

        return $refs;
    }

    private static function entityOwner(ModuleRegistry $registry, string $handle): ?string
    {
        return ($registry->compiled()->entities[$handle] ?? null)?->module;
    }
}
