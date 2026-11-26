<?php

declare(strict_types=1);

namespace App\Lifecycle;

use App\Scenario\Scenario;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Panels\Contracts\SavedViewInventory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packman\Panels\SavedViews\Contracts\SavedViewRepository;
use Packman\Panels\SavedViews\Data\SaveViewInput;
use Packman\Panels\SavedViews\Data\ViewContext;
use RuntimeException;

final class SavedViews implements SavedViewInventory
{
    private const string OWNER = 'axiomasoft/packman-panels';

    public function connection(DataScope $scope): ConnectionInterface
    {
        $db = DB::connection();
        resolve(CurrentWorkScope::class)->assert($scope, $db);

        return $db;
    }

    public function scopes(DataScope $scope): array
    {
        $this->connection($scope);

        return array_map(static fn ($view) => [Views::context(), $view], Views::scopes());
    }

    public function targets(DataScope $scope, ViewContext $context, string $operation, ?string $id, ?SaveViewInput $input): array
    {
        $directory = $this->scopes($scope);

        if ($context != Views::context() || ($input !== null && ! in_array($input->scope, array_column($directory, 1)))) {
            throw new RuntimeException('Unknown private or shared namespace.');
        }

        if ($id !== null) {
            $found = [];
            foreach ($directory as [$actor, $namespace]) {
                foreach (resolve(SavedViewRepository::class)->viewsFor($actor, $namespace) as $view) {
                    if ($view->id === $id) {
                        $found[] = $view;
                    }
                }
            }

            if (count($found) !== 1 || ($input !== null && $found[0]->scope != $input->scope)) {
                throw new RuntimeException('Missing or ambiguous saved view.');
            }

            if ($operation === 'delete') {
                $lease = Scenario::connection('control')->table('scenario_exclusions')->where(['namespace' => self::namespace($scope), 'target' => Inventory::MODULE])->first();

                if ($lease !== null) {
                    $this->verify($scope, new OwnerLease(self::OWNER, $lease->namespace, $lease->plan, $lease->token));
                    $closure = resolve(DatabaseScopeControl::class)->moduleClosure($scope, new ModuleRef(Inventory::MODULE));

                    if ($closure === $lease->plan && DB::table('scenario_view_remedies')->where(['id' => $id, 'revision' => $found[0]->revision,
                        'plan' => $lease->plan, 'scope_hash' => hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR))])->delete() === 1) {
                        return [new ModuleRef(Inventory::PROVIDER)];
                    }
                }
            }
        }

        return [new ModuleRef(Inventory::MODULE), new ModuleRef(Inventory::PROVIDER)];
    }

    private static function namespace(DataScope $scope): string
    {
        return self::OWNER.'/'.hash('sha256', json_encode($scope, JSON_THROW_ON_ERROR));
    }

    public function hold(DataScope $scope, ModuleRef $module, LifecyclePlanRef $plan, ?OwnerLease $previous): OwnerLease
    {
        $key = ['namespace' => self::namespace($scope), 'target' => $module->package];
        $table = Scenario::connection('control')->table('scenario_exclusions');

        if ($previous === null) {
            $table->insertOrIgnore([...$key, 'plan' => $plan->id, 'token' => (string) Str::uuid7()]);
        }
        $row = $table->where($key)->where('plan', $plan->id)->sole();
        $lease = new OwnerLease(self::OWNER, $row->namespace, $plan->id, $row->token);

        if ($previous !== null && $lease != $previous) {
            throw new RuntimeException('Saved view lease changed.');
        }
        DB::transaction(static function () use ($lease, $previous): void {
            DB::table('scenario_view_directory_lock')->lockForUpdate()->sole();
            $local = DB::table('scenario_view_directory_leases');

            if ($previous === null) {
                $local->insertOrIgnore(['token' => $lease->token, 'plan' => $lease->revision, 'namespace' => $lease->namespace]);
            }
            $local->where(['token' => $lease->token, 'plan' => $lease->revision, 'namespace' => $lease->namespace])->sole();
        });

        return $lease;
    }

    public function verify(DataScope $scope, OwnerLease $lease): void
    {
        if ($lease->namespace !== self::namespace($scope)
            || ! DB::table('scenario_view_directory_leases')->where(['token' => $lease->token, 'plan' => $lease->revision, 'namespace' => $lease->namespace])->exists()
            || ! Scenario::connection('control')->table('scenario_exclusions')
                ->where(['namespace' => $lease->namespace, 'token' => $lease->token, 'plan' => $lease->revision])->exists()) {
            throw new RuntimeException('Saved view directory is unavailable.');
        }
    }

    public function release(DataScope $scope, OwnerLease $lease): void
    {
        $this->connection($scope);

        if ($lease->owner !== self::OWNER || $lease->namespace !== self::namespace($scope)) {
            throw new RuntimeException('Foreign saved view lease.');
        }
        Inventory::releaseControl($lease);
        DB::transaction(static function () use ($lease): void {
            DB::table('scenario_view_directory_lock')->lockForUpdate()->sole();
            $key = ['namespace' => $lease->namespace, 'plan' => $lease->revision, 'token' => $lease->token];

            if (DB::table('scenario_view_directory_releases')->where($key)->exists()) {
                return;
            }

            if (DB::table('scenario_view_directory_leases')->where($key)->delete() !== 1) {
                throw new RuntimeException('The original directory lease and its release receipt are unavailable.');
            }
            DB::table('scenario_view_directory_releases')->insert($key);
        });
    }
}
