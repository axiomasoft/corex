<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Enums\SchemaScope;
use RuntimeException;

final class ScenarioSetup
{
    public static function install(string $case, bool $cloud): array
    {
        $control = Scenario::connection('control');
        ScopeControlSchema::install($control);
        FixtureLifecycleOperators::install($control);
        $schema = $control->getSchemaBuilder();
        $schema->create('scenario_binding_locations', static function (Blueprint $table): void {
            $table->string('binding_id')->primary();
            $table->uuid('account_id');
            $table->string('role');
        });
        $schema->create('scenario_search_resolutions', static function (Blueprint $table): void {
            $table->id();
            $table->string('target');
        });

        if ($cloud && $case !== 'workspace-sweep') {
            $jobMigrations = glob(database_path('migrations/*_create_jobs_table.php'));

            if (count($jobMigrations) !== 1 || Artisan::call('migrate', [
                '--database' => 'scenario_control', '--path' => [$jobMigrations[0]], '--realpath' => true, '--force' => true,
            ]) !== 0) {
                throw new RuntimeException('Could not install the Laravel database broker. '.Artisan::output());
            }
        }
        $migrator = resolve(SchemaMigrator::class);
        $ran = [];

        if ($cloud) {
            $ran['control'] = $migrator->migrate(SchemaScope::Central, 'pgsql', true);
        }

        $accounts = $cloud
            ? ['a' => Scenario::ACCOUNT_A, 'b' => $case === 'binding-restore' ? Scenario::ACCOUNT_A : Scenario::ACCOUNT_B]
            : ['a' => Scenario::BOXED];
        foreach ($accounts as $role => $account) {
            $database = Scenario::connection($role);
            $generation = $case === 'binding-restore' && $role === 'b' ? 2 : 1;
            $binding = Scenario::binding('binding-'.$role, $generation, 'a', $account);
            self::installTarget($database, $binding, $cloud);
            $ran[$role] = $migrator->migrate(SchemaScope::Tenant, 'scenario_'.$role, true);
            self::seedTarget($database, $role, $case, $cloud);

            if ($cloud && $case === 'worker-scope') {
                $database->getSchemaBuilder()->create('scenario_service_grants', static function (Blueprint $table): void {
                    $table->string('service')->primary();
                    $table->uuid('account');
                });
                $database->table('scenario_service_grants')->insert(['service' => 'corex.scheduler-sweep', 'account' => $account]);
            }
            $control->table('scenario_binding_locations')->insert(['binding_id' => $binding->bindingId, 'account_id' => $account, 'role' => $role]);

            if ($case !== 'binding-restore' || $role === 'a') {
                resolve(DatabaseScopeControl::class)->register($account, $binding);

                if ($cloud) {
                    self::seedRootAccount($control, $database, $account, $role);
                }
            }
        }

        if (Artisan::call('modules:compile') !== 0) {
            throw new RuntimeException('Could not compile the installed catalogue. '.Artisan::output());
        }

        FixtureLifecycleOperators::provision($case);

        if ($case === 'workspace-sweep') {
            ScenarioQueue::inScope(Scenario::BOXED, Scenario::W2, Scenario::USER_B, static fn () => resolve(ModuleLifecycle::class)->enable(
                'axiomasoft/corex-tasks', cascade: true,
            ));
        }

        return $ran;
    }

    private static function installTarget(Connection $database, PhysicalBinding $binding, bool $cloud): void
    {
        $schema = $database->getSchemaBuilder();
        $schema->create('scenario_physical_binding', static function (Blueprint $table): void {
            $table->string('binding_id')->primary();
            $table->unsignedBigInteger('generation');
            $table->string('incarnation');
        });
        $database->table('scenario_physical_binding')->insert([
            'binding_id' => $binding->bindingId, 'generation' => $binding->bindingGeneration, 'incarnation' => $binding->accountIncarnation,
        ]);
        $schema->create('scenario_account_grants', static fn (Blueprint $table) => $table->uuid('principal_id')->primary());
        $schema->create('scenario_field_grants', static function (Blueprint $table): void {
            $table->uuid('principal_id');
            $table->string('workspace_id');
            $table->string('entity');
            $table->string('record_id');
            $table->string('field_id');
            $table->string('field_use');
            $table->primary(['principal_id', 'workspace_id', 'entity', 'record_id', 'field_id', 'field_use'], 'scenario_grants_pk');
        });
        $schema->create('scenario_records', static function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->text('marker');
            $table->text('tenant_a_label');
            $table->text('tenant_b_label');
        });
        $schema->create('scenario_effects', static function (Blueprint $table): void {
            $table->string('reference')->primary();
            $table->string('marker');
        });

        if (! $cloud) {
            $schema->create('users', static function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('email');
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestampTz('deleted_at')->nullable();
            });
            $schema->create('wsp_workspaces', static function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('slug');
                $table->string('name');
                $table->string('type');
                $table->string('path');
                $table->timestampTz('deleted_at')->nullable();
            });
            $schema->create('wsp_workspace_members', static function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('workspace_id')->constrained('wsp_workspaces');
                $table->foreignUuid('user_id')->constrained('users');
                $table->string('status');
            });
        }
    }

    private static function seedTarget(Connection $database, string $role, string $case, bool $cloud): void
    {
        $owner = $role === 'b' && $case !== 'binding-restore' ? Scenario::USER_B : Scenario::USER_A;
        $workspace = $role === 'b' && $case !== 'binding-restore' ? Scenario::W2 : Scenario::W1;
        $workspaces = $case === 'workspace-sweep' ? [Scenario::W1, Scenario::W2] : [$workspace];
        foreach ($workspaces as $id) {
            $ref = Scenario::workspace($id);
            $database->table('wsp_workspaces')->insert(['id' => $id, 'slug' => $ref->slug, 'name' => $ref->slug, 'type' => $ref->type, 'path' => $ref->path]);
        }
        $principals = $case === 'workspace-sweep' ? [Scenario::USER_A => Scenario::W1, Scenario::USER_B => Scenario::W2] : [$owner => $workspace];
        foreach ($principals as $id => $membership) {
            $database->table('users')->insert(['id' => $id, 'email' => $id.'@example.test', 'name' => 'Verified fixture principal']);
            $database->table('wsp_workspace_members')->insert(['id' => (string) Str::uuid7(), 'workspace_id' => $membership, 'user_id' => $id, 'status' => 'active']);
            $database->table('scenario_account_grants')->insert(['principal_id' => $id]);
        }
        $database->table('scenario_records')->insert([
            'id' => Scenario::RECORD, 'marker' => 'equipment '.$role, 'tenant_a_label' => 'visible-a', 'tenant_b_label' => 'visible-b',
        ]);
        $readGrant = [
            'principal_id' => $owner, 'workspace_id' => $cloud ? $workspace : 'account', 'entity' => 'scenario.equipment',
            'record_id' => Scenario::RECORD, 'field_use' => 'read',
        ];
        $database->table('scenario_field_grants')->insert([
            [...$readGrant, 'field_id' => 'record'],
            [...$readGrant, 'field_id' => 'tenant_'.$role.'_label'],
        ]);

        if (in_array($case, ['worker-scope', 'binding-restore'], true)) {
            $database->table('scenario_field_grants')->insert([...$readGrant, 'field_id' => 'marker', 'field_use' => 'search']);
        }
    }

    private static function seedRootAccount(Connection $control, Connection $target, string $account, string $role): void
    {
        $cluster = (string) Str::uuid7();
        $owner = (string) Str::uuid7();
        $control->table('root_clusters')->insert(['id' => $cluster, 'name' => 'cluster-'.$role, 'pg_host_writer' => 'writer.example.test', 'pgbouncer_host' => 'pool.example.test', 'app_db_user' => 'corex']);
        $control->table('root_identities')->insert(['id' => $owner, 'email' => $owner.'@example.test']);
        $control->table('root_accounts')->insert([
            'id' => $account, 'slug' => 'account-'.$role, 'cluster_id' => $cluster, 'db_name' => $target->getDatabaseName(),
            'status' => 'active', 'owner_identity_id' => $owner, 'template_version' => 1, 'embedding_model' => 'fixture', 'embedding_dim' => 16,
        ]);
    }
}
