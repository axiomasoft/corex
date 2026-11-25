<?php

declare(strict_types=1);

namespace App\Lifecycle;

use App\Scenario\Scenario;
use App\Scenario\ScenarioQueue;
use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Inventory implements LifecycleInventory
{
    public const string MODULE = 'thirdparty/custom-attribute-source';

    public const string ENTITY = 'cas.equipment';

    public const string PROVIDER = 'axiomasoft/corex-panels';

    public static function scope(): DataScope
    {
        return resolve(DataScopeFactory::class)->forCurrentPrincipal(resolve(TenantContextResolver::class)->current());
    }

    public static function enter(string $account, ?string $workspace, Closure $work): mixed
    {
        $user = match ($account) {
            Scenario::ACCOUNT_A => Scenario::USER_A,
            Scenario::ACCOUNT_B => Scenario::USER_B,
        };

        return ScenarioQueue::inScope($account, $workspace, $user, static fn () => $work(self::scope()));
    }

    public function revision(DataScope $operator, ModuleRef $target): string
    {
        resolve(CurrentWorkScope::class)->assert($operator);
        $row = Scenario::connection('control')->table('scenario_deployment')->sole();
        $sites = Scenario::connection('control')->table('scenario_binding_locations')->orderBy('role')->get()->all();

        if (array_column($sites, 'account_id') !== [Scenario::ACCOUNT_A, Scenario::ACCOUNT_B]
            || array_column($sites, 'role') !== ['a', 'b']) {
            throw new RuntimeException('Incomplete physical owner directory.');
        }

        $directory = DB::table('scenario_view_directory_lock')->sole()->revision;

        return hash('sha256', json_encode([$sites, $row->membership_revision, $row->retention_revision, $directory], JSON_THROW_ON_ERROR));
    }

    public function scopes(DataScope $operator, ModuleRef $target): array
    {
        $this->revision($operator, $target);
        $workspace = match ($operator->accountId) {
            Scenario::ACCOUNT_A => Scenario::W1,
            Scenario::ACCOUNT_B => Scenario::W2,
        };

        if ($operator->workspaceId !== null || DB::table('wsp_workspaces')->pluck('id')->all() !== [$workspace]
            || DB::table('users')->pluck('id')->all() !== [$operator->principalId]) {
            throw new RuntimeException('The physical inventory changed. Review private and shared scopes.');
        }

        return [$operator, self::enter($operator->accountId, $workspace, static fn (DataScope $scope) => $scope)];
    }

    public static function releaseControl(OwnerLease $lease): void
    {
        $control = Scenario::connection('control');

        if ($control->transactionLevel() !== 0 || DB::connection()->transactionLevel() !== 0) {
            throw new RuntimeException('Lease release requires independent durable commits.');
        }
        $control->transaction(static function () use ($control, $lease): void {
            $control->table('scenario_deployment')->lockForUpdate()->sole();
            $key = ['token' => $lease->token, 'plan' => $lease->revision, 'namespace' => $lease->namespace];
            $receipt = ['owner' => $lease->owner, ...$key];

            if ($control->table('scenario_exclusion_releases')->where($receipt)->exists()) {
                return;
            }

            if ($control->table('scenario_exclusions')->where($key)->delete() !== 1) {
                throw new RuntimeException('The original control lease and its release receipt are unavailable.');
            }
            $control->table('scenario_exclusion_releases')->insert($receipt);
        });
    }

    public function owners(DataScope $scope, ModuleRef $target): array
    {
        return ['axiomasoft/packman-panels', ExternalResources::OWNER];
    }

    public function run(DataScope $scope, Closure $operation): mixed
    {
        $work = static function () use ($scope, $operation): mixed {
            resolve(CurrentWorkScope::class)->assert($scope, DB::connection());

            return $operation();
        };

        return self::scope() == $scope ? $work() : self::enter($scope->accountId, $scope->workspaceId, $work);
    }
}
