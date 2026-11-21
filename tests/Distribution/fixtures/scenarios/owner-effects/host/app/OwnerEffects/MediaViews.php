<?php

declare(strict_types=1);

namespace App\OwnerEffects;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\LifecyclePlanRef;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Data\OwnerLease;
use CoreX\Panels\Contracts\SavedViewInventory;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Panels\SavedViews\Contracts\ViewAccessPolicy;
use Packman\Panels\SavedViews\Data\SaveViewInput;
use Packman\Panels\SavedViews\Data\ViewAction;
use Packman\Panels\SavedViews\Data\ViewContext;
use Packman\Panels\SavedViews\Data\ViewScope;
use Packman\Panels\SavedViews\Data\ViewSubject;
use RuntimeException;

/** This host exposes read-only views. Its immutable user roster includes every private namespace. */
final class MediaViews implements SavedViewInventory, ViewAccessPolicy
{
    public function connection(DataScope $scope): ConnectionInterface
    {
        $db = DB::connection();
        app(CurrentWorkScope::class)->assert($scope, $db);

        return $db;
    }

    public function scopes(DataScope $scope): array
    {
        $this->connection($scope);
        $actor = new ViewContext(app(TenantContextResolver::class)->current(), $scope->principalKind, $scope->principalId);
        $directory = [];
        foreach (app(ModuleRegistry::class)->compiled()->entities as $entity) {
            $directory[] = [$actor, new ViewScope($entity->handle, $scope->workspaceId, null, null)];
            $directory[] = [$actor, new ViewScope($entity->handle, $scope->workspaceId, $scope->principalKind, $scope->principalId)];
        }

        if ($directory === []) {
            throw new RuntimeException('The installed entity roster is unavailable.');
        }

        return $directory;
    }

    public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool
    {
        return $action === ViewAction::Read && in_array([$context, $subject->scope], $this->scopes(Effects::scope()));
    }

    public function targets(DataScope $scope, ViewContext $context, string $operation, ?string $id, ?SaveViewInput $input): array
    {
        if ($operation !== 'inspect') {
            throw new RuntimeException('This installed Media host is read-only for saved views.');
        }

        return [new ModuleRef(Effects::MEDIA), new ModuleRef(Effects::MODULE)];
    }

    public function hold(DataScope $scope, ModuleRef $module, LifecyclePlanRef $plan, ?OwnerLease $previous): OwnerLease
    {
        $db = $this->connection($scope);
        $key = ['namespace' => Effects::identity($scope), 'target' => $module->package];

        if ($previous === null) {
            $db->table('scenario_media_directory_leases')->insertOrIgnore([...$key, 'token' => (string) Str::uuid7(), 'plan' => $plan->id]);
        }
        $row = $db->table('scenario_media_directory_leases')->where($key)->where('plan', $plan->id)->sole();
        $lease = new OwnerLease('axiomasoft/packman-panels', $row->namespace, $plan->id, $row->token);

        if ($previous !== null && $previous != $lease) {
            throw new RuntimeException('The original view lease changed.');
        }

        return $lease;
    }

    public function verify(DataScope $scope, OwnerLease $lease): void
    {
        if ($lease->namespace !== Effects::identity($scope) || ! $this->connection($scope)->table('scenario_media_directory_leases')
            ->where(['namespace' => $lease->namespace, 'plan' => $lease->revision, 'token' => $lease->token])->exists()) {
            throw new RuntimeException('Media directory lease unavailable.');
        }
    }

    public function release(DataScope $scope, OwnerLease $lease): void
    {
        $this->verify($scope, $lease);
        $this->connection($scope)->table('scenario_media_directory_leases')->where('token', $lease->token)->delete();
    }
}
