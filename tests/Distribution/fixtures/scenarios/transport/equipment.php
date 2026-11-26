<?php

declare(strict_types=1);

use App\BulkTransfer\FixtureSetup;
use App\BulkTransfer\InputIdentity;
use App\BulkTransfer\Runtime;
use App\FieldSearch\EquipmentLocaleSource;
use App\FieldSearch\EquipmentOwner;
use App\Scenario\Scenario;
use App\Scenario\ScenarioClock;
use App\TransportProof\CarrierSnapshot;
use App\TransportProof\Wire;
use App\TransportProof\WorkerAuthority;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\OperationKey;
use CoreX\CustomFields\Schemas\Contracts\FieldCatalogue;
use CoreX\CustomFields\Schemas\Contracts\FieldSchemas;
use CoreX\CustomFields\Schemas\Data\FieldDefinition;
use CoreX\CustomFields\Schemas\Data\FieldId;
use CoreX\CustomFields\Schemas\Data\FieldSchemaChange;
use CoreX\CustomFields\Types\Data\FieldTypeId;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Index\Contracts\ActiveGenerations;
use Equipment\Registry\Equipment;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;

require getcwd().'/vendor/autoload.php';
require __DIR__.'/setup.php';
Scenario::boot();
$proof = new Scenario;
$db = Runtime::tenant();
$mode = $argv[1] ?? throw new LogicException('Missing equipment transport phase.');
$before = ['connections' => FixtureSetup::verifyTargets()];
$after = ['phase' => $mode, 'php' => PHP_VERSION];
$refs = [];
$input = ['kind' => 'import', 'source' => 'equipment', 'row_limit' => 1, 'time_limit_seconds' => 4];
$base = '/api/v2';
$bulk = $base.'/bulk-operations';
$direct = $base.'/equipment/bulk-transfer';
$tool = 'equipment_transfer__v2';
$paths = ['fields' => $base.'/entities/eq.equipment/fields', 'search' => $base.'/entities/eq.equipment/search'];

switch ($mode) {
    case 'setup':
        $after['setup'] = setupTransport($proof, $db, false);
        $db->getSchemaBuilder()->create('installed_metadata_reads', static function (Blueprint $table): void {
            $table->string('phase')->primary();
            $table->jsonb('transcript');
        });
        $db->getSchemaBuilder()->create('installed_metadata_workers', static function (Blueprint $table): void {
            $table->uuid('account_id');
            $table->uuid('workspace_id');
            $table->string('service');
        });
        foreach ([WorkerAuthority::SCHEMA, WorkerAuthority::SEARCH] as $service) {
            $db->table('installed_metadata_workers')->insert(['account_id' => Scenario::BOXED, 'workspace_id' => Scenario::W1, 'service' => $service]);
        }
        $db->getSchemaBuilder()->create('scenario_equipment_locales', static function (Blueprint $table): void {
            $table->string('asset_key');
            $table->string('field_id');
            $table->jsonb('translations');
            $table->primary(['asset_key', 'field_id']);
        });
        Runtime::scoped(static function (): void {
            Equipment::query()->forceCreate(['asset_key' => InputIdentity::FIRST, 'name' => 'Registry compressor']);
            Equipment::query()->forceCreate(['asset_key' => InputIdentity::SECOND, 'name' => 'Registry valve']);
        });
        foreach ([EquipmentLocaleSource::PUBLIC_ID => ['en' => 'Compressor', 'ru-RU' => 'Компрессор'], EquipmentLocaleSource::PRIVATE_ID => ['en' => 'secret-caption']] as $field => $translations) {
            $db->table('scenario_equipment_locales')->insert(['asset_key' => InputIdentity::FIRST, 'field_id' => $field, 'translations' => json_encode($translations, JSON_THROW_ON_ERROR)]);
        }
        $proof->same(2, $db->table('eq_equipment')->count(), 'Real installed equipment owns the metadata records.');

        break;

    case 'async-http':
        $before['ledger'] = transferLedger($db);
        $accepted = Wire::http('POST', $direct, $input, ['key' => 'async-parity']);
        $proof->same(202, $accepted['status'], 'Declared native equipment endpoint has real owner acceptance.');
        $proof->same($accepted['body']['data']['status_url'], $accepted['location'], 'Actual 202 Location and result status URI agree.');
        $id = $accepted['body']['data']['operation_id'];
        $proof->same($bulk.'/'.$id, $accepted['location'], 'Location points to the native management status.');
        $refs['operation'] = $id;
        $after += ['accepted' => $accepted, 'ledger' => transferLedger($db)];

        break;

    case 'async-mcp':
        $before['ledger'] = transferLedger($db);
        $listed = Wire::rpc('tools/list');
        $entry = array_find($listed['body']['result']['tools'], static fn (array $tool): bool => $tool['name'] === 'equipment_transfer__v2');
        $proof->same('v2:equipment.bulk.transfer', $entry['operationRef'], 'Real compiled equipment exposure is visible.');
        $called = Wire::call($tool, $input, 'async-parity');
        $gateway = Wire::http('POST', $bulk, $input, ['key' => 'async-parity']);
        $data = $called['body']['result']['structuredContent'];
        $proof->same(false, $called['body']['result']['isError'], 'MCP successfully replays async acceptance.');
        $proof->same(202, $gateway['status'], 'Actual scoped Bulk gateway dispatches the canonical target.');
        $proof->same($data, $gateway['body']['data'], 'MCP and the gateway return the same typed acceptance.');
        $proof->same($data['status_url'], $gateway['location'], 'Gateway replay retains the owner status URI.');
        $proof->same($before['ledger'], transferLedger($db), 'Gateway and direct MCP replay leave all intent, receipt and budget rows unchanged.');
        $status = Wire::http('GET', $data['status_url']);
        $proof->check(in_array($status['status'], [200, 202], true), 'Actual authorized status location resolves.');
        $foreign = Wire::http('GET', $data['status_url'], options: ['token' => 'transport-workspace']);
        $proof->same(404, $foreign['status'], 'Known accepted status is opaque across workspace.');
        $refs['operation'] = $data['operation_id'];
        $after += ['list' => $listed, 'called' => $called, 'gateway' => $gateway, 'status' => $status, 'foreign' => $foreign, 'ledger' => $before['ledger']];

        break;

    case 'bulk-work':
        $before['operation'] = (array) $db->table('sys_api_bulk_operations')->sole();
        for ($step = 0; $step < 4 && Runtime::bulk('import')->status !== 'completed'; $step++) {
            $proof->same(0, Runtime::work($step), 'Actual standalone Bulk command step succeeds.');
        }
        $row = Runtime::bulk('import');
        $proof->same('completed', $row->status, 'Bounded worker visits the real equipment transfer owner.');
        $sink = Scenario::connection('sink');
        $effect = (array) $sink->table('bulk_sink_equipment')->sole();
        $proof->same('EQ-001', $effect['serial'], 'The independent real sink committed the admitted row.');
        $after += ['operation' => (array) $row, 'transfer' => Runtime::transfer($row->id), 'sink' => ['connection' => Scenario::describe($sink), 'effect' => $effect]];

        break;

    case 'bulk-routes':
        $id = Runtime::id('import');
        $show = Wire::http('GET', $bulk.'/'.$id);
        $result = Wire::http('GET', $bulk.'/'.$id.'/result');
        $failures = Wire::http('GET', $bulk.'/'.$id.'/failures?limit=1');
        $proof->same([200, 200, 200], [$show['status'], $result['status'], $failures['status']], 'Native show, result and failures resolve after worker completion.');
        $proof->same(Runtime::transfer($id), $result['body']['data']['id'], 'Installed Bulk result links to the public transfer ID.');
        $proof->same([1, 1], [$result['body']['data']['stats']['applied'], $result['body']['data']['stats']['invalid']], 'Owner result has one applied and one invalid row.');
        $pending = Wire::http('POST', $bulk, $input, ['key' => 'async-cancel']);
        $proof->same(202, $pending['status'], 'Separate real intent is cancellable.');
        $cancel = Wire::http('POST', $pending['location'].'/cancel', [], ['key' => 'async-cancel-command']);
        $proof->check(in_array($cancel['status'], [200, 202], true) && in_array($cancel['body']['data']['status'], ['cancelled', 'cancelling'], true), 'Actual named cancel route changes the pending intent.');
        $before['ledger'] = transferLedger($db);
        $ports = [
            ['POST', $bulk, $input], ['GET', $bulk.'/'.$id, []],
            ['GET', $bulk.'/'.$id.'/result', []], ['GET', $bulk.'/'.$id.'/failures?limit=1', []],
            ['POST', $pending['location'].'/cancel', []],
        ];
        $after += compact('show', 'result', 'failures', 'pending', 'cancel');
        $after['negative'] = unsupportedVersions($proof, $ports, []);
        $after['ledger'] = transferLedger($db);
        $proof->same($before['ledger'], $after['ledger'], 'Unsupported native paths create no extra transfer or budget writes.');

        break;

    case 'index':
    case 'schema-index':
    case 'replace-index':
        $after['generation'] = Runtime::scoped(static function () use ($mode) {
            $context = app(TenantContextResolver::class)->current();

            return app(WorkerAuthority::class)->runAs($context, WorkerAuthority::SEARCH, static function () use ($context, $mode) {
                $scope = app(DataScopeFactory::class)->forCurrentPrincipal($context);

                return EquipmentOwner::rebuild($scope, 'installed-metadata-'.$mode);
            });
        });
        $proof->check($after['generation']->generation > 0, 'The real backend publishes an effective generation.');

        break;

    case 'metadata-first':
        $after['views'] = metadataViews($proof, $paths, 'en');
        $after['openapi'] = Wire::http('GET', '/api/v2/openapi.json');
        $proof->same(200, $after['openapi']['status'], 'Installed static OpenAPI is reachable.');
        saveMetadata($db, $mode, $after);

        break;

    case 'metadata-same':
        $prior = loadMetadata($db, 'metadata-first');
        foreach ($paths as $kind => $uri) {
            $cached = Wire::http('GET', $uri, options: ['headers' => ['HTTP_IF_NONE_MATCH' => $prior['views'][$kind]['etag'], 'HTTP_ACCEPT_LANGUAGE' => 'en']]);
            $proof->same([304, 0, $prior['views'][$kind]['etag']], [$cached['status'], $cached['size'], $cached['etag']], 'Only the current same-view cache tag returns a bodyless 304.');
            $after['views'][$kind] = $cached;
            $alternate = Wire::http('GET', $uri, options: ['token' => 'transport-beta', 'headers' => ['HTTP_IF_NONE_MATCH' => $prior['views'][$kind]['etag']]]);
            $proof->same(200, $alternate['status'], 'An independently trusted client gets a fresh authorized view.');
            $proof->check($alternate['etag'] !== $prior['views'][$kind]['etag'], 'ETags preserve verified client separation.');
            $after['different_client'][$kind] = $alternate;
            $foreign = Wire::http('GET', $uri, options: ['token' => 'transport-workspace', 'headers' => ['HTTP_IF_NONE_MATCH' => $prior['views'][$kind]['etag']]]);
            $proof->same(403, $foreign['status'], 'Knowing another workspace ETag does not authorize read.');
            $after['foreign'][$kind] = $foreign;
            $missing = Wire::http('GET', $uri, options: ['headers' => [
                'HTTP_AUTHORIZATION' => '', 'HTTP_IF_NONE_MATCH' => $prior['views'][$kind]['etag'],
            ]]);
            $proof->same([401, null, null], [$missing['status'], $missing['etag'], $missing['identity']], 'The old ETag grants no access without a trusted identity.');
            $after['missing_identity'][$kind] = $missing;
        }

        break;

    case 'metadata-revoke':
        foreach (['fields', 'search'] as $kind) {
            $before['requests'][$kind] = Scenario::barrierRead('metadata-'.$kind.'-policy');
            $proof->same([true, 0], [$before['requests'][$kind]['grant'], $before['requests'][$kind]['level']], 'The concurrent request reached the final check with its original grant and no open snapshot.');
            $proof->check($before['requests'][$kind]['connection']['session'] !== Scenario::describe($db)['session'], 'Revocation uses a distinct PostgreSQL session.');
        }
        $proof->same(1, $db->table('installed_transport_grants')->where(['workspace_id' => Scenario::W1, 'client_id' => 'alpha-client', 'grant' => 'equipment-schema'])->delete(), 'Current owner policy is revoked without changing client authentication.');
        $after['owner_grant'] = false;

        break;

    case 'metadata-fields-race':
    case 'metadata-search-race':
        $kind = $mode === 'metadata-fields-race' ? 'fields' : 'search';
        $prior = loadMetadata($db, 'metadata-first')['views'][$kind];
        $response = Wire::http('GET', $paths[$kind], options: ['headers' => [
            'HTTP_IF_NONE_MATCH' => $prior['etag'], 'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]]);
        $snapshot = Scenario::barrierRead('metadata-'.$kind.'-policy-snapshot');
        $proof->same([false, 1, 'repeatable read'], [$snapshot['grant'], $snapshot['level'], $snapshot['isolation']], 'The same physical owner takes a fresh snapshot after revocation.');
        $proof->same([403, null], [$response['status'], $response['etag']], 'The old conditional request denies release after the concurrent revocation.');
        $proof->check(! isset($response['body']['data']), 'The concurrent denial releases no previous metadata.');
        $after += compact('snapshot', 'response');

        break;

    case 'metadata-denied':
        $prior = loadMetadata($db, 'metadata-first');
        foreach ($paths as $kind => $uri) {
            $denied = Wire::http('GET', $uri, options: ['headers' => ['HTTP_IF_NONE_MATCH' => $prior['views'][$kind]['etag']]]);
            $proof->same([403, null], [$denied['status'], $denied['etag']], 'Owner authorization runs before conditional release.');
            $proof->check(! isset($denied['body']['data']['fields']), 'Known ETag reveals no field metadata on denial.');
            $after['denied'][$kind] = $denied;
        }
        grantTransport($db, Scenario::W1, Scenario::USER_A, 'alpha-client', ['equipment-schema']);

        break;

    case 'schema-change':
        $after['change'] = Runtime::scoped(static function (DataScope $scope) use ($proof): array {
            $entity = EquipmentOwner::entity();
            $fields = app(FieldCatalogue::class);
            $schemas = app(FieldSchemas::class);
            $previous = $fields->snapshot($scope, $entity);
            $definition = new FieldDefinition(
                id: new FieldId(EquipmentOwner::PRIORITY), entity: $entity, machineKey: 'priority', type: new FieldTypeId('text'),
                owner: 'runtime', uses: [FieldUse::Read, FieldUse::Filter],
            );
            $plan = $schemas->plan($scope, FieldSchemaChange::add($definition), $previous->revision);
            $ref = $schemas->apply($scope, $plan->ref, $plan->digest, new OperationKey('transport-visible-field', 1));
            $worker = app(WorkerAuthority::class);
            $context = app(TenantContextResolver::class)->current();
            $commands = [];
            for ($steps = 0; $steps < 32 && $schemas->inspect($scope, $ref)->state !== 'completed'; $steps++) {
                $commands[] = $worker->runAs($context, WorkerAuthority::SCHEMA, static fn (): int => Artisan::call('corex:custom-fields:work', ['--limit' => 1]));
                app(ScenarioClock::class)->advance(1);
            }
            $status = $schemas->inspect($scope, $ref);
            $current = $fields->snapshot($scope, $entity);
            $proof->same('completed', $status->state, 'Installed owner publishes the real field schema change.');
            $proof->check($previous->revision != $current->revision, 'Published owner revision advances.');
            $proof->check($commands !== [] && array_unique($commands) === [0], 'The registered worker executes each bounded phase.');

            return ['operation' => $ref, 'before_revision' => $previous->revision, 'after_revision' => $current->revision, 'commands' => $commands];
        });
        $prior = loadMetadata($db, 'metadata-first');
        $after['fields'] = Wire::http('GET', $paths['fields'], options: ['headers' => ['HTTP_IF_NONE_MATCH' => $prior['views']['fields']['etag']]]);
        $proof->same(200, $after['fields']['status'], 'A current schema change refreshes the known field view.');
        $proof->check($prior['views']['fields']['etag'] !== $after['fields']['etag'], 'Actual owner revision invalidates Field ETag.');
        $proof->check(in_array(EquipmentOwner::PRIORITY, array_column($after['fields']['body']['data']['fields'], 'id'), true), 'The newly published stable field is discoverable.');

        break;

    case 'metadata-schema':
        $prior = loadMetadata($db, 'metadata-first');
        $after['views'] = metadataViews($proof, $paths, 'en');
        $after['conditional_search'] = Wire::http('GET', $paths['search'], options: ['headers' => [
            'HTTP_IF_NONE_MATCH' => $prior['views']['search']['etag'], 'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]]);
        $proof->same(200, $after['conditional_search']['status'], 'The old Search tag refreshes after schema publication.');
        $proof->same($after['views']['search']['etag'], $after['conditional_search']['etag'], 'The refreshed Search tag identifies the current view.');
        $after['active'] = Runtime::scoped(static fn (DataScope $scope) => app(ActiveGenerations::class)->active($scope, EquipmentOwner::entity()));
        $proof->check($after['active'] !== null, 'Readiness is backed by the actual current published generation.');
        saveMetadata($db, $mode, $after);

        break;

    case 'metadata-views':
        $prior = loadMetadata($db, 'metadata-schema');
        $initial = loadMetadata($db, 'metadata-first');
        $after['active'] = Runtime::scoped(static fn (DataScope $scope) => app(ActiveGenerations::class)->active($scope, EquipmentOwner::entity()));
        $after['backend'] = Wire::http('GET', $paths['search'], options: ['headers' => ['HTTP_IF_NONE_MATCH' => $prior['views']['search']['etag'], 'HTTP_ACCEPT_LANGUAGE' => 'en']]);
        $proof->same(200, $after['backend']['status'], 'A different real backend generation invalidates the conditional read.');
        $proof->check($after['backend']['etag'] !== $prior['views']['search']['etag'] && $after['active']->generation !== $prior['active']['generation'], 'Backend witnessed a different generation.');
        $proof->same($prior['views']['search']['body']['data']['schema_revision'], $after['backend']['body']['data']['schema_revision'], 'Backend rotation is independent of runtime schema revision.');
        foreach ($paths as $kind => $uri) {
            $english = $kind === 'search' ? $after['backend'] : Wire::http('GET', $uri, options: ['headers' => ['HTTP_ACCEPT_LANGUAGE' => 'en']]);
            $russian = Wire::http('GET', $uri, options: ['headers' => ['HTTP_IF_NONE_MATCH' => $english['etag'], 'HTTP_ACCEPT_LANGUAGE' => 'ru-RU']]);
            $proof->same(200, $russian['status'], 'Locale change refreshes metadata visibility.');
            $proof->same('ru-RU', $russian['body']['data']['capabilities']['locale'], 'Real negotiated locale appears in the authorized view.');
            $proof->check($russian['etag'] !== $english['etag'], 'Effective locale changes the conditional witness.');
            $after['locales'][$kind] = compact('english', 'russian');
        }
        $after['openapi'] = Wire::http('GET', '/api/v2/openapi.json');
        $proof->same($initial['openapi']['sha256'], $after['openapi']['sha256'], 'Actual static carrier bytes stay unchanged across owner schema, locale and index transitions.');
        $proof->check(! str_contains(json_encode([$after['backend'], $after['locales']], JSON_THROW_ON_ERROR), $after['active']->uid), 'No private backend UID is on the wire.');
        $after['carrier'] = CarrierSnapshot::capture($proof, 'equipment-carriers');
        $proof->check(in_array('equipment.bulk.transfer', $after['carrier']['operations'], true), 'The equipment carrier is generated from its installed owning declaration.');
        $after['negative'] = unsupportedVersions($proof, array_map(static fn (string $uri): array => ['GET', $uri, []], $paths), []);

        break;

    default:
        throw new LogicException('Unknown equipment transport phase '.$mode);
}

$proof->report($db, $refs, $before, $after);

function transferLedger(Connection $db): array
{
    $budget = Scenario::connection('budget');

    return [
        'intents' => $db->table('sys_api_bulk_operations')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
        'receipts' => $db->table('sys_api_idempotency')->orderBy('key_hash')->get()->map(static fn (object $row): array => (array) $row)->all(),
        'budget' => $budget->table('sys_api_budget_reservations')->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all(),
    ];
}

function metadataViews(Scenario $proof, array $paths, string $locale): array
{
    $views = [];
    foreach ($paths as $kind => $uri) {
        $view = Wire::http('GET', $uri, options: ['headers' => ['HTTP_ACCEPT_LANGUAGE' => $locale]]);
        $proof->same(200, $view['status'], 'The actual metadata owner serves the permitted view.');
        $proof->check(is_string($view['etag']) && preg_match('/\A"[a-f0-9]{64}"\z/D', $view['etag']) === 1 && str_contains($view['cache'], 'private'), 'Metadata supports a qualified private ETag.');
        $proof->same('eq.equipment', $view['body']['data']['entity'], 'Metadata reads current equipment owner schema.');
        $proof->check(in_array(EquipmentOwner::SERIAL, array_column($view['body']['data']['fields'], 'id'), true), 'The authorized view includes the installed equipment serial field.');
        $proof->check(! str_contains(json_encode($view['body']['data'], JSON_THROW_ON_ERROR), EquipmentLocaleSource::PRIVATE_ID), 'Protected field does not enter published metadata.');
        $views[$kind] = $view;
    }

    return $views;
}

function saveMetadata(Connection $db, string $phase, array $data): void
{
    $db->table('installed_metadata_reads')->insert(['phase' => $phase, 'transcript' => json_encode($data, JSON_THROW_ON_ERROR)]);
}

function loadMetadata(Connection $db, string $phase): array
{
    return json_decode($db->table('installed_metadata_reads')->where('phase', $phase)->value('transcript'), true, flags: JSON_THROW_ON_ERROR);
}
