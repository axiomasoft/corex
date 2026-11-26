<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\PlanDigest;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;

final class FixtureLifecycleOperators
{
    private const string GRANTS = 'scenario_lifecycle_grants';

    public static function register(Gate $gate): void
    {
        foreach (['inspect', 'enable', 'upgrade', 'repair', 'disable', 'detach', 'purge', 'remove-provider'] as $ability) {
            $gate->define('corex.lifecycle.'.$ability, static function (ScopePrincipal $subject, DataScope $scope, ModuleRef $target, ?LifecyclePlanRef $ref = null, ?PlanDigest $digest = null) use ($ability): bool {
                $context = app(CurrentWorkScope::class)->assert($scope);

                if ($subject->kind !== 'user' || $subject->id !== $scope->principalId || $scope->principalKind !== $subject->kind
                    || app(DataScopeFactory::class)->forCurrentPrincipal($context) != $scope) {
                    return false;
                }
                $query = Scenario::connection('control')->table(self::GRANTS)->where(self::key($scope, $target->package, $ability));

                if ($ability === 'purge' && ($ref !== null || $digest !== null)) {
                    return $ref !== null && $digest !== null && $query->where('plan', $ref->id)->where('digest', $digest->value)->exists();
                }

                if ($ability === 'remove-provider') {
                    return $ref === null && $digest !== null && $query->where('plan', '')->where('digest', $digest->value)->exists();
                }

                return $query->where('plan', '')->where('digest', '')->exists();
            });
        }
    }

    public static function install(Connection $control): void
    {
        $control->getSchemaBuilder()->create(self::GRANTS, static function (Blueprint $table): void {
            $table->char('scope', 64);
            $table->string('package');
            $table->string('ability');
            $table->string('plan');
            $table->string('digest');
            $table->primary(['scope', 'package', 'ability', 'plan', 'digest']);
        });
    }

    /** The host provisions each authorized action for the verified physical account and execution frame. */
    public static function grant(DataScope $scope, string $package, string $ability, ?LifecyclePlanRef $ref = null, ?PlanDigest $digest = null): void
    {
        app(CurrentWorkScope::class)->assert($scope);
        Scenario::connection('control')->table(self::GRANTS)->insertOrIgnore([
            ...self::key($scope, $package, $ability), 'plan' => $ref?->id ?? '', 'digest' => $digest?->value ?? '',
        ]);
    }

    public static function revoke(DataScope $scope, string $package, string $ability): void
    {
        app(CurrentWorkScope::class)->assert($scope);
        Scenario::connection('control')->table(self::GRANTS)->where(self::key($scope, $package, $ability))->delete();
    }

    public static function provision(string $case): void
    {
        [$workspace, $actor, $packages] = match ($case) {
            'task-lifetime' => [Scenario::W1, Scenario::USER_A, ['axiomasoft/corex-tasks']],
            'workspace-sweep' => [Scenario::W2, Scenario::USER_B, ['axiomasoft/corex-tasks']],
            'field-write', 'ddl-claim' => [Scenario::W1, Scenario::USER_A, [FieldScenario::EXTENSION]],
            'field-search' => [Scenario::W1, Scenario::USER_A, ['equipment/registry']],
            'provider-unknown' => [Scenario::W1, Scenario::USER_A, ['axiomasoft/corex-notifications', 'axiomasoft/corex-workflow']],
            'notification-visibility' => [Scenario::W1, Scenario::USER_A, ['axiomasoft/corex-notifications']],
            default => [null, null, []],
        };

        if ($actor !== null) {
            ScenarioQueue::inScope(Scenario::BOXED, $workspace, $actor, static function () use ($packages): void {
                $scope = app(DataScopeFactory::class)->forCurrentPrincipal(app(TenantContextResolver::class)->current());
                foreach ($packages as $package) {
                    foreach (['inspect', 'enable'] as $ability) {
                        self::grant($scope, $package, $ability);
                    }
                }
            });
        }
    }

    private static function key(DataScope $scope, string $package, string $ability): array
    {
        return ['scope' => hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR)), 'package' => $package, 'ability' => $ability];
    }
}
