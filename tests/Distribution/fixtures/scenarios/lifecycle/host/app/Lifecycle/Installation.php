<?php

declare(strict_types=1);

namespace App\Lifecycle;

use App\Scenario\FieldScenario;
use App\Scenario\FixtureLifecycleOperators;
use App\Scenario\Scenario;
use App\Scenario\ScenarioSetup;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use RuntimeException;

final class Installation
{
    public static function setup(): array
    {
        $control = Scenario::connection('control');
        foreach (['control', 'a', 'b'] as $role) {
            $name = getenv('COREX_SCENARIO_DB_'.strtoupper($role));
            $row = Scenario::connection($role)->selectOne("SELECT current_database() AS db, current_setting('server_version_num') AS version");

            if (! preg_match('/^corex_scenario_[0-9a-f]{16}_'.$role.'_test$/D', $name) || $row->db !== $name
                || ! in_array($row->version, ['160014', '180006'], true) || $row->version !== getenv('COREX_SCENARIO_PG_VERSION')
                || ! in_array(PHP_VERSION, ['8.4.27', '8.5.12'], true)) {
                throw new RuntimeException('Unexpected PHP or PostgreSQL scenario identity.');
            }
        }
        $ran = ScenarioSetup::install('lifecycle', true);
        $control->unprepared(<<<'SQL'
            CREATE TABLE scenario_seed_origins(physical text, owner_table text, record text, owner text NOT NULL, PRIMARY KEY(physical,owner_table,record));
            CREATE TABLE scenario_deployment(id integer PRIMARY KEY CHECK (id=1), composition text NOT NULL,
              membership_revision integer NOT NULL, retention_revision integer NOT NULL, rollout uuid, promotion text);
            CREATE TABLE scenario_owner_certificates(rollout uuid, account uuid, digest text, proof jsonb, PRIMARY KEY(rollout,account));
            CREATE TABLE scenario_unavailable(account uuid PRIMARY KEY);
            CREATE TABLE scenario_generations(uid text PRIMARY KEY, task text, namespace text UNIQUE NOT NULL, preparing boolean NOT NULL, confirmed boolean NOT NULL, retained boolean NOT NULL);
            CREATE TABLE scenario_ddl_inventory(namespace text PRIMARY KEY, index_oid text NOT NULL, parent_oid text NOT NULL, definition text NOT NULL, retired boolean NOT NULL DEFAULT false);
            CREATE TABLE scenario_cleanup_receipts(effect text PRIMARY KEY, evidence text NOT NULL);
            CREATE TABLE scenario_drop_intents(effect text PRIMARY KEY, namespace text NOT NULL, uid text NOT NULL, receipt text);
            CREATE TABLE scenario_exclusions(namespace text, target text, plan uuid NOT NULL, token uuid UNIQUE NOT NULL, PRIMARY KEY(namespace,target));
            CREATE TABLE scenario_exclusion_releases(token uuid PRIMARY KEY, owner text NOT NULL, namespace text NOT NULL, plan uuid NOT NULL);
            CREATE TABLE scenario_lifecycle_observations(label text PRIMARY KEY, evidence jsonb NOT NULL);
            CREATE FUNCTION scenario_membership_change() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN
              PERFORM id FROM scenario_deployment WHERE id=1 FOR UPDATE;
              IF EXISTS (SELECT 1 FROM scenario_deployment WHERE rollout IS NOT NULL)
                 OR EXISTS (SELECT 1 FROM scenario_exclusions) THEN
                RAISE EXCEPTION 'deployment membership frozen' USING ERRCODE='55006';
              END IF;
              UPDATE scenario_deployment SET membership_revision=membership_revision+1;
              RETURN NULL;
            END $$;
            CREATE TRIGGER scenario_guard_membership BEFORE INSERT OR UPDATE OR DELETE OR TRUNCATE ON scenario_binding_locations
              FOR EACH STATEMENT EXECUTE FUNCTION scenario_membership_change();
            SQL);
        $control->table('scenario_deployment')->insert(['id' => 1, 'composition' => Deployment::digests()[0]->value, 'membership_revision' => 1, 'retention_revision' => 1]);
        foreach (['control' => ['root_clusters', 'root_accounts', 'root_identities'], 'a' => ['users', 'wsp_workspaces', 'wsp_workspace_members'],
            'b' => ['users', 'wsp_workspaces', 'wsp_workspace_members']] as $role => $tables) {
            foreach ($tables as $table) {
                foreach (Scenario::connection($role)->table($table)->pluck('id') as $id) {
                    $control->table('scenario_seed_origins')->insert(['physical' => $role === 'control' ? 'central' : 'binding-'.$role,
                        'owner_table' => $table, 'record' => $id, 'owner' => 'axiomasoft/corex-tenancy']);
                }
            }
        }
        foreach ([Scenario::ACCOUNT_A => Scenario::W1, Scenario::ACCOUNT_B => Scenario::W2] as $account => $workspace) {
            Inventory::enter($account, null, static function (DataScope $scope): void {
                foreach (['enable', 'inspect', 'disable', 'detach', 'purge', 'repair'] as $ability) {
                    FixtureLifecycleOperators::grant($scope, Inventory::MODULE, $ability);
                }
                foreach ([Inventory::PROVIDER, 'axiomasoft/packman-panels'] as $provider) {
                    FixtureLifecycleOperators::grant($scope, $provider, 'remove-provider', digest: Deployment::digests($provider)[1]);
                }
                foreach (['enable', 'inspect', 'disable'] as $action) {
                    FixtureLifecycleOperators::grant($scope, HostModule::PACKAGE, $action);
                }
                app(ModuleLifecycle::class)->enable(Inventory::MODULE);
                DB::unprepared(<<<'SQL'
                    CREATE TABLE scenario_view_directory_lock(singleton integer PRIMARY KEY CHECK(singleton=1), revision integer NOT NULL);
                    INSERT INTO scenario_view_directory_lock VALUES(1,1);
                    CREATE TABLE scenario_view_directory_leases(token uuid PRIMARY KEY, plan uuid NOT NULL, namespace text NOT NULL);
                    CREATE TABLE scenario_view_directory_releases(token uuid PRIMARY KEY, plan uuid NOT NULL, namespace text NOT NULL);
                    CREATE FUNCTION scenario_view_membership_change() RETURNS trigger LANGUAGE plpgsql AS $$
                    BEGIN
                      PERFORM singleton FROM scenario_view_directory_lock FOR UPDATE;
                      IF EXISTS(SELECT 1 FROM scenario_view_directory_leases) THEN
                        RAISE EXCEPTION 'private/shared view membership frozen' USING ERRCODE='55006';
                      END IF;
                      UPDATE scenario_view_directory_lock SET revision=revision+1;
                      RETURN NULL;
                    END $$;
                    SQL);
                foreach (['users', 'wsp_workspaces', 'wsp_workspace_members'] as $directory) {
                    DB::statement("CREATE TRIGGER scenario_view_membership BEFORE INSERT OR UPDATE OR DELETE OR TRUNCATE ON {$directory}
                        FOR EACH STATEMENT EXECUTE FUNCTION scenario_view_membership_change()");
                }
                DB::getSchemaBuilder()->create('scenario_view_remedies', static function (Blueprint $table): void {
                    $table->uuid('id')->primary();
                    $table->string('revision');
                    $table->string('scope_hash');
                    $table->uuid('plan');
                });
            });
            Inventory::enter($account, $workspace, static function (DataScope $scope) use ($account): void {
                DB::table('scenario_field_grants')->insert(['principal_id' => $scope->principalId, 'workspace_id' => $scope->workspaceId,
                    'entity' => Inventory::ENTITY, 'record_id' => Scenario::RECORD, 'field_id' => FieldScenario::INSPECTION, 'field_use' => 'read']);
                self::record($account, ExternalResources::provision($scope, $account === Scenario::ACCOUNT_B));

                if ($account === Scenario::ACCOUNT_B) {
                    $fields = app(FieldCatalogue::class)
                        ->snapshot($scope, new EntityHandle(Inventory::ENTITY));
                    self::record('materialized-fields', $fields->revision);
                }
            });
        }

        return $ran;
    }

    public static function record(string $label, mixed $value): void
    {
        Scenario::connection('control')->table('scenario_lifecycle_observations')->insert(['label' => $label,
            'evidence' => json_encode($value, JSON_THROW_ON_ERROR)]);
    }

    public static function read(string $label): array
    {
        return json_decode(Scenario::connection('control')->table('scenario_lifecycle_observations')->where('label', $label)->sole()->evidence, true, flags: JSON_THROW_ON_ERROR);
    }
}
