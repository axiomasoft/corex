<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\Scenario;
use App\Scenario\ScenarioSetup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Enums\SchemaScope;
use RuntimeException;

final class FixtureSetup
{
    public static function verifyTargets(): array
    {
        $names = [];
        foreach (['control', 'a', 'budget', 'sink'] as $role) {
            $expected = getenv('COREX_SCENARIO_DB_'.strtoupper($role));

            if (! is_string($expected) || preg_match('/\Acorex_scenario_[a-f0-9]{16}_'.$role.'_test\z/D', $expected) !== 1) {
                throw new RuntimeException('Missing runner-allocated '.$role.' database.');
            }
            $database = Scenario::connection($role);
            $identity = Scenario::describe($database);
            $version = (int) $database->selectOne('SHOW server_version_num')->server_version_num;

            if ($identity['database'] !== $expected || $identity['namespace'] !== 'public'
                || $version !== (int) getenv('COREX_SCENARIO_PG_VERSION') || ! in_array($version, [160014, 180006], true)) {
                throw new RuntimeException('Unexpected PostgreSQL identity for '.$role.'.');
            }
            $names[$role] = $identity;
        }

        if (count(array_unique(array_column($names, 'database'))) !== 4 || Scenario::describe(app('db')->connection())['database'] !== $names['a']['database']) {
            throw new RuntimeException('The tenant, control, budget and sink must have independent physical identities.');
        }

        return $names;
    }

    public static function install(): void
    {
        ScenarioSetup::install('bulk-transfer', false);
        app(SchemaMigrator::class)->migrate(SchemaScope::Dedicated);
        $tenant = Scenario::connection('a');
        $schema = $tenant->getSchemaBuilder();

        $workspace = Scenario::workspace(Scenario::W2);
        $tenant->table('wsp_workspaces')->insert(['id' => $workspace->id, 'slug' => $workspace->slug, 'name' => $workspace->slug, 'type' => $workspace->type, 'path' => $workspace->path]);
        $tenant->table('wsp_workspace_members')->insert(['id' => (string) Str::uuid7(), 'workspace_id' => $workspace->id, 'user_id' => Scenario::USER_A, 'status' => 'active']);

        $schema->create('bulk_fixture_revisions', static function (Blueprint $table): void {
            $table->char('schema_hash', 64);
            $table->string('codec');
            $table->string('mapping');
            $table->string('snapshot_revision');
        });
        $tenant->table('bulk_fixture_revisions')->insert([
            'schema_hash' => hash('sha256', 'equipment-schema/1'), 'codec' => 'equipment-ndjson/1',
            'mapping' => 'equipment-mapping/1', 'snapshot_revision' => 'snapshot-1',
        ]);
        $schema->create('bulk_fixture_export', static function (Blueprint $table): void {
            $table->integer('ordinal')->primary();
            $table->uuid('id')->unique();
            $table->string('serial');
            $table->string('revision');
        });
        $tenant->table('bulk_fixture_export')->insert([
            ['ordinal' => 1, 'id' => InputIdentity::FIRST, 'serial' => 'EQ-101', 'revision' => 'v1'],
            ['ordinal' => 2, 'id' => InputIdentity::SECOND, 'serial' => 'EQ-102', 'revision' => 'v1'],
        ]);

        $grants = [
            ['import-export.transfer', '*', 'write', ['record']],
            ['import-export.transfer', '*', 'read', ['record']],
            ['import-export.manifest', '*', 'read', ['record']],
            ['import-export.manifest', '*', 'export', ['record']],
            [InputIdentity::ENTITY, InputIdentity::FIRST, 'write', ['record', InputIdentity::SERIAL]],
            [InputIdentity::ENTITY, InputIdentity::FIRST, 'read', ['record', InputIdentity::SERIAL]],
            [InputIdentity::ENTITY, InputIdentity::SECOND, 'read', ['record', InputIdentity::SERIAL]],
            [InputIdentity::ENTITY, InputIdentity::FIRST, 'export', ['record', InputIdentity::SERIAL]],
            [InputIdentity::ENTITY, InputIdentity::SECOND, 'export', ['record', InputIdentity::SERIAL]],
        ];
        foreach ([Scenario::W1, Scenario::W2] as $scope) {
            foreach ($grants as [$entity, $record, $use, $fields]) {
                foreach ($fields as $field) {
                    $tenant->table('scenario_field_grants')->insert([
                        'principal_id' => Scenario::USER_A, 'workspace_id' => $scope, 'entity' => $entity,
                        'record_id' => $record, 'field_use' => $use, 'field_id' => $field,
                    ]);
                }
            }
        }

        $sink = Scenario::connection('sink');
        $sink->statement('CREATE TABLE bulk_sink_equipment (scope text NOT NULL, record_id uuid NOT NULL, serial text NOT NULL, PRIMARY KEY (scope, record_id))');
        $sink->statement('CREATE TABLE bulk_sink_receipts (scope text NOT NULL, effect_key text NOT NULL, digest text NOT NULL, fence text NOT NULL, record_id uuid NOT NULL, receipt text NOT NULL, PRIMARY KEY (scope, effect_key))');
        $sink->statement('CREATE TABLE bulk_sink_journal (sequence bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY, action text NOT NULL, effect_key text NOT NULL, outcome text NOT NULL, provider_pid integer NOT NULL, backend_pid integer NOT NULL)');
    }
}
