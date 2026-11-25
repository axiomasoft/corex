<?php

declare(strict_types=1);

use App\BulkTransfer\FixtureSetup;
use App\Scenario\FixtureLifecycleOperators;
use App\Scenario\Scenario;
use App\Scenario\ScenarioQueue;
use App\Scenario\ScenarioSetup;
use App\TransportProof\NotesManifest;
use App\TransportProof\Wire;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Enums\SchemaScope;

const TRANSPORT_RECORD = 'note:integer:42';
const TRANSPORT_SECRET = 'installed-private-label';

function setupTransport(Scenario $proof, Connection $a, bool $cloud): array
{
    if ($cloud) {
        $migrations = ScenarioSetup::install('transport', true);
        $migrations['budget'] = resolve(SchemaMigrator::class)->migrate(SchemaScope::Dedicated);
        $workspace = Scenario::workspace(Scenario::W2);
        $a->table('wsp_workspaces')->insert(['id' => $workspace->id, 'slug' => $workspace->slug, 'name' => $workspace->slug, 'type' => $workspace->type, 'path' => $workspace->path]);
        $a->table('wsp_workspace_members')->insert(['id' => (string) Str::uuid7(), 'workspace_id' => $workspace->id, 'user_id' => Scenario::USER_A, 'status' => 'active']);
        $b = Scenario::connection('b');
        setupTransportTables($b);
        grantTransport($b, Scenario::W2, Scenario::USER_B, 'alpha-client', ['client', 'fields.describe']);
    } else {
        FixtureSetup::install('transport');
        $migrations = ['tenant' => 'native tenant migration + dedicated budget'];
        $jobMigrations = glob(database_path('migrations/*_create_jobs_table.php'));

        if (count($jobMigrations) !== 1 || Artisan::call('migrate', [
            '--database' => 'scenario_control', '--path' => [$jobMigrations[0]], '--realpath' => true, '--force' => true,
        ]) !== 0) {
            throw new RuntimeException('Could not install the Laravel database broker. '.Artisan::output());
        }
        $migrations['control_queue'] = Artisan::output();
    }

    setupTransportTables($a);
    $a->table('installed_notes_subjects')->insert(['id' => TRANSPORT_RECORD, 'workspace_id' => Scenario::W1, 'private_label' => TRANSPORT_SECRET]);
    grantTransport($a, Scenario::W1, Scenario::USER_A, 'alpha-client', [
        'client', 'notes.write', 'record:'.TRANSPORT_RECORD, 'read-private-label', 'write-title',
        'fields.describe', 'search.describe', 'equipment-schema', 'equipment.bulk.transfer', 'bulk.manage',
    ]);
    grantTransport($a, Scenario::W1, Scenario::USER_A, 'beta-client', ['client', 'fields.describe', 'search.describe']);
    grantTransport($a, Scenario::W2, Scenario::USER_A, 'alpha-client', ['client', 'notes.write', 'fields.describe', 'search.describe', 'bulk.manage']);
    grantTransport($a, null, Scenario::USER_A, 'webhook-client', ['client', 'webhooks.manage', 'webhooks.replay']);
    $control = Scenario::connection('control');
    $control->getSchemaBuilder()->create('scenario_broker_receipts', static function (Blueprint $table): void {
        $table->uuid('message_id')->primary();
        $table->char('digest', 64);
    });
    $control->getSchemaBuilder()->create('installed_webhook_receiver', static function (Blueprint $table): void {
        $table->string('effect_key')->primary();
        $table->string('delivery_ref');
        $table->char('wire_sha256', 64);
        $table->char('signature_sha256', 64);
        $table->unsignedBigInteger('backend_pid');
    });
    $host = $cloud ? Scenario::ACCOUNT_A : Scenario::BOXED;
    $active = ScenarioQueue::inScope($host, null, Scenario::USER_A, static function () use ($cloud, $proof): array {
        $context = resolve(TenantContextResolver::class)->current();
        $scope = resolve(DataScopeFactory::class)->forCurrentPrincipal($context);
        $owners = [NotesManifest::OWNER];

        if (! $cloud) {
            $owners[] = 'equipment/registry';
        }
        foreach ($owners as $owner) {
            FixtureLifecycleOperators::grant($scope, $owner, 'inspect');
            FixtureLifecycleOperators::grant($scope, $owner, 'enable');
            resolve(ModuleLifecycle::class)->enable($owner, cascade: true);
            $proof->same(true, resolve(ModuleRegistry::class)->isActive($owner, $context), 'Compiled installed owner activated.');
        }

        return ['owners' => $owners, 'scope' => $scope];
    });

    return compact('active', 'migrations');
}

function setupTransportTables(Connection $db): void
{
    $db->getSchemaBuilder()->create('installed_transport_grants', static function (Blueprint $table): void {
        $table->string('workspace_id');
        $table->uuid('principal_id');
        $table->string('client_id');
        $table->string('grant');
        $table->primary(['workspace_id', 'principal_id', 'client_id', 'grant'], 'installed_transport_grants_pk');
    });
    $db->getSchemaBuilder()->create('installed_notes_subjects', static function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->uuid('workspace_id');
        $table->string('private_label');
    });
    $db->getSchemaBuilder()->create('installed_notes', static function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('resource_id');
        $table->string('title');
        $table->string('private_label');
        $table->uuid('principal_id');
        $table->uuid('workspace_id');
    });
}

function grantTransport(Connection $db, ?string $workspace, string $actor, string $client, array $grants): void
{
    foreach ($grants as $grant) {
        $db->table('installed_transport_grants')->insert([
            'workspace_id' => $workspace ?? 'account', 'principal_id' => $actor, 'client_id' => $client, 'grant' => $grant,
        ]);
    }
}

function unsupportedVersions(Scenario $proof, array $ports, array $options): array
{
    $negative = [];
    foreach ($ports as [$method, $uri, $body]) {
        foreach (['v1', 'v3'] as $major) {
            $path = preg_replace('#\A/api/v2/#', '/api/'.$major.'/', $uri);
            $response = Wire::http($method, $path, $body, ['key' => 'unsupported-major', ...$options]);
            $proof->same([404, null], [$response['status'], $response['route']], 'Native method and known path are not mounted for '.$major.'.');
            $negative[] = $response;
        }
    }

    return $negative;
}
