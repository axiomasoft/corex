<?php

declare(strict_types=1);

namespace App\OwnerEffects;

use App\Scenario\Scenario;
use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\LifecycleInventory;
use CoreX\Foundation\Contracts\ReferenceOwners;
use CoreX\Foundation\Data\ModuleRef;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class MediaInventory implements LifecycleInventory, ReferenceOwners
{
    public static function install(): void
    {
        $control = Scenario::connection('control');
        $control->unprepared(<<<'SQL'
            CREATE FUNCTION scenario_sealed_sites() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'sealed physical Media sites' USING ERRCODE='55006'; END $$;
            CREATE TRIGGER scenario_fixed_sites BEFORE INSERT OR UPDATE OR DELETE OR TRUNCATE ON scenario_binding_locations
            FOR EACH STATEMENT EXECUTE FUNCTION scenario_sealed_sites();
            SQL);
        $control->statement('CREATE TABLE scenario_effect_origins(physical text, relation text, record text, producer text, owners jsonb NOT NULL, PRIMARY KEY(physical,relation,record))');
        foreach (['control' => ['root_accounts', 'root_identities', 'root_clusters'], 'a' => ['users', 'wsp_workspaces', 'wsp_workspace_members'],
            'b' => ['users', 'wsp_workspaces', 'wsp_workspace_members']] as $role => $relations) {
            $db = Scenario::connection($role);
            foreach ($relations as $relation) {
                foreach ($db->table($relation)->pluck('id') as $id) {
                    $control->table('scenario_effect_origins')->insert(['physical' => $role, 'relation' => $relation, 'record' => $id,
                        'producer' => 'axiomasoft/corex-tenancy', 'owners' => json_encode(['axiomasoft/corex-tenancy'])]);
                }
            }

            if ($role !== 'control') {
                $db->statement('CREATE TABLE scenario_media_directory_leases(namespace text, target text, plan uuid NOT NULL, token uuid NOT NULL UNIQUE, PRIMARY KEY(namespace,target))');
                // This bounded installation seals its two account rosters once every user and workspace is provisioned.
                $db->unprepared("CREATE FUNCTION scenario_sealed_media_roster() RETURNS trigger LANGUAGE plpgsql AS \$\$
                    BEGIN RAISE EXCEPTION 'sealed Media owner directory' USING ERRCODE='55006'; END \$\$");
                foreach ($relations as $relation) {
                    $db->statement("CREATE TRIGGER scenario_fixed_roster BEFORE INSERT OR UPDATE OR DELETE OR TRUNCATE ON {$relation}
                        FOR EACH STATEMENT EXECUTE FUNCTION scenario_sealed_media_roster()");
                }
            }
        }
    }

    public static function recordGrant(DataScope $scope): void
    {
        $site = $scope->accountId === Scenario::ACCOUNT_A ? 'a' : 'b';
        $id = DB::table('aut_direct_grants')->where(['principal_type' => 'user', 'principal_id' => $scope->principalId,
            'permission_key' => 'app.'.Effects::ENTITY.'.read'])->whereNull('revoked_at')->sole()->id;
        Scenario::connection('control')->table('scenario_effect_origins')->insertOrIgnore(['physical' => $site, 'relation' => 'aut_direct_grants',
            'record' => $id, 'producer' => 'axiomasoft/corex-access', 'owners' => json_encode(['axiomasoft/corex-access', Effects::MODULE])]);
    }

    public function resolve(DataScope $scope, string $producer, string $kind, array $witness): array
    {
        app(CurrentWorkScope::class)->assert($scope);
        $role = str_starts_with($kind, 'root_') ? 'control' : match ($scope->accountId) {
            Scenario::ACCOUNT_A => 'a', Scenario::ACCOUNT_B => 'b',
        };
        $record = $witness['record'] ?? throw new RuntimeException('Original Media fixture provenance is unavailable.');
        $owners = Scenario::connection('control')->table('scenario_effect_origins')->where(['physical' => $role, 'relation' => $kind,
            'producer' => $producer, 'record' => $record])->sole()->owners;

        return array_map(static fn ($name) => new ModuleRef($name), json_decode($owners, true, flags: JSON_THROW_ON_ERROR));
    }

    public function scopes(DataScope $operator, ModuleRef $target): array
    {
        $this->revision($operator, $target);
        $workspaces = DB::table('wsp_workspaces')->orderBy('id')->pluck('id')->all();
        $expected = $operator->accountId === Scenario::ACCOUNT_A ? [Scenario::W1, Scenario::W2] : [Scenario::W2];

        if ($operator->workspaceId !== null || $workspaces !== $expected || DB::table('users')->pluck('id')->all() !== [$operator->principalId]) {
            throw new RuntimeException('Incomplete physical Media owner directory.');
        }
        $scopes = [$operator];
        foreach ($workspaces as $id) {
            $scopes[] = Effects::enter($operator->accountId, $id, static fn (DataScope $scope) => $scope);
        }

        return $scopes;
    }

    public function owners(DataScope $scope, ModuleRef $target): array
    {
        return ['axiomasoft/packman-panels'];
    }

    public function revision(DataScope $operator, ModuleRef $target): string
    {
        app(CurrentWorkScope::class)->assert($operator);
        $sites = Scenario::connection('control')->table('scenario_binding_locations')->orderBy('role')->get()->all();

        if (array_column($sites, 'account_id') !== [Scenario::ACCOUNT_A, Scenario::ACCOUNT_B] || array_column($sites, 'role') !== ['a', 'b']) {
            throw new RuntimeException('Media physical deployment changed.');
        }

        return hash('sha256', json_encode([$sites, $operator, 'retain-original-releases-and-content-receipts/1'], JSON_THROW_ON_ERROR));
    }

    public function run(DataScope $scope, Closure $operation): mixed
    {
        $work = static function () use ($operation, $scope): mixed {
            app(CurrentWorkScope::class)->assert($scope, DB::connection());

            return $operation();
        };

        return Effects::scope() == $scope ? $work() : Effects::enter($scope->accountId, $scope->workspaceId, $work);
    }
}
