<?php

declare(strict_types=1);

use App\ProductProof\Http;
use App\Scenario\FixtureLifecycleOperators;
use App\Scenario\Scenario;
use App\Scenario\ScenarioQueue;
use App\Scenario\ScenarioSetup;
use Composer\InstalledVersions;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Packman\Modules\Contracts\Lifecycle\ModuleLifecycle;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Schema\Contracts\Migration\SchemaMigrator;
use Packman\Schema\Enums\SchemaScope;

require getcwd().'/vendor/autoload.php';
Scenario::boot();
$proof = new Scenario;
$mode = $argv[1] ?? throw new LogicException('Missing CRM phase.');
$db = Scenario::connection('a');
$before = ['connections' => crmConnections()];
$after = ['phase' => $mode, 'php' => PHP_VERSION];
$refs = [];
$base = '/api/v2';

switch ($mode) {
    case 'setup':
        $migrated = ScenarioSetup::install('product', true);
        $migrated['budget'] = app(SchemaMigrator::class)->migrate(SchemaScope::Dedicated);
        installCrmGrants($db, Scenario::W1, Scenario::USER_A);
        installCrmGrants(Scenario::connection('b'), Scenario::W2, Scenario::USER_B);
        $workspace = Scenario::workspace(Scenario::W2);
        $db->table('wsp_workspaces')->insert(['id' => $workspace->id, 'slug' => $workspace->slug, 'name' => $workspace->slug, 'type' => $workspace->type, 'path' => $workspace->path]);
        $db->table('wsp_workspace_members')->insert(['id' => (string) Str::uuid7(), 'workspace_id' => $workspace->id, 'user_id' => Scenario::USER_A, 'status' => 'active']);
        grantCrm($db, $workspace->id, Scenario::USER_A);
        $path = InstalledVersions::getInstallPath('flex/crm') ?: throw new RuntimeException('FlexCRM is not installed.');
        $manifest = json_decode(file_get_contents($path.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $edges = array_filter($manifest['require'], static fn (string $name): bool => $name === 'axiomasoft/corex' || str_starts_with($name, 'axiomasoft/corex-'), ARRAY_FILTER_USE_KEY);
        $proof->same(['axiomasoft/corex', 'axiomasoft/corex-intl'], array_keys($edges), 'The installed product keeps only Core and Intl production edges.');
        $proof->check(! is_link('vendor/flex/crm') && realpath($path) === realpath('vendor/flex/crm'), 'FlexCRM resolves to a physical installed copy.');
        $proof->same(productDigest('flex-crm'), productDigest($path), 'Product package is a byte-identical copy of the composing app input.');
        $proof->same(0, $db->table('crm_companies')->count(), 'There are no preseeded CRM rows.');
        $after += ['product' => ['version' => InstalledVersions::getPrettyVersion('flex/crm'), 'edges' => $edges, 'tree_sha256' => productDigest($path)], 'migrated' => $migrated];

        break;

    case 'before-enable':
        $response = Http::send('POST', $base.'/companies', ['name' => 'Must not exist']);
        $proof->same(404, $response['status'], 'The installed but disabled module refuses the actual handler.');
        $proof->same(0, $db->table('crm_companies')->count(), 'Disabled module did not write a company.');
        $after += ['response' => $response, 'companies' => 0];

        break;

    case 'enable':
        foreach ([[Scenario::ACCOUNT_A, Scenario::W1, Scenario::USER_A], [Scenario::ACCOUNT_A, Scenario::W2, Scenario::USER_A], [Scenario::ACCOUNT_B, Scenario::W2, Scenario::USER_B]] as [$account, $workspace, $actor]) {
            $after['activations'][] = ScenarioQueue::inScope($account, $workspace, $actor, static function () use ($proof): array {
                $tenant = app(TenantContextResolver::class)->current();
                $scope = app(DataScopeFactory::class)->forCurrentPrincipal($tenant);
                FixtureLifecycleOperators::grant($scope, 'flex/crm', 'enable');
                FixtureLifecycleOperators::grant($scope, 'flex/crm', 'inspect');
                app(ModuleLifecycle::class)->enable('flex/crm', cascade: true);
                $active = app(ModuleRegistry::class)->isActive('flex/crm', $tenant);
                $proof->same(true, $active, 'Public lifecycle activates the product in the trusted workspace.');

                return ['scope' => $scope, 'active' => $active];
            });
        }
        $after['profile_openapi'] = Http::send('GET', $base.'/openapi.json');
        $company = $after['profile_openapi']['body']['paths']['/companies/{companyId}'] ?? [];
        $proof->same(200, $after['profile_openapi']['status'], 'The installed FlexCRM profile publishes its own mounted OpenAPI document.');
        $proof->same(['crm.company.show', 'crm.company.update', 'crm.company.delete'], array_map(static fn (string $verb): ?string => $company[$verb]['operationId'] ?? null, ['get', 'patch', 'delete']), 'The product document includes its registered keyed company routes.');

        break;

    case 'create':
        $company = Http::send('POST', $base.'/companies', ['name' => ' Fleet  owner ', 'legal_name' => 'Fleet Legal', 'domain' => 'fleet.test']);
        $contact = Http::send('POST', $base.'/contacts', ['name_first' => 'Jane', 'emails' => [['value' => 'jane@example.test']]]);
        $proof->same([201, 201], [$company['status'], $contact['status']], 'Actual product handlers created both owner records.');
        $companyId = $company['body']['data']['id'];
        $contactId = $contact['body']['data']['id'];
        $link = Http::send('POST', $base.'/contacts/'.$contactId.'/companies', ['company_id' => $companyId, 'label' => 'service', 'is_primary' => true]);
        $proof->same(201, $link['status'], 'Association uses the real product handler.');
        $refs = ['company' => $companyId, 'contact' => $contactId, 'link' => $link['body']['data']['id']];
        $after += ['company' => $company, 'contact' => $contact, 'association' => $link, 'owner' => crmOwner($db)];

        break;

    case 'read':
    case 'read-updated':
        $owner = crmOwner($db);
        $id = $owner['company']['id'];
        $response = Http::send('GET', $base.'/companies/'.$id);
        $contacts = Http::send('GET', $base.'/contacts');
        $proof->same([200, 200], [$response['status'], $contacts['status']], 'Fresh kernel requests use current product scope.');
        $proof->same($id, $response['body']['data']['id'], 'Fresh read preserves the physical UUID primary key.');
        $proof->same(Scenario::W1, $response['body']['data']['workspace_id'], 'Read is scoped to the current workspace.');
        $proof->same($mode === 'read' ? 'Fleet owner' : 'Fleet updated', $response['body']['data']['name'], 'Fresh read sees committed owner normalization.');
        $proof->same($owner['contact']['id'], $contacts['body']['data']['items'][0]['id'], 'Fresh contact read resolves the original UUID.');
        $after += ['company' => $response, 'contacts' => $contacts, 'owner' => $owner];
        $refs = ['company' => $id];

        break;

    case 'mutate':
        $owner = crmOwner($db);
        $path = $base.'/companies/'.$owner['company']['id'];
        $before['owner'] = $owner;
        $protected = Http::send('PATCH', $path, ['workspace_id' => Scenario::W2], 'crm-protected');
        $proof->same([403, '/workspace_id'], [$protected['status'], $protected['body']['path']], 'An unlisted protected field is refused before mutation.');
        $db->table('installed_crm_grants')->where(['ability' => 'crm.company.update', 'field' => '/domain'])->delete();
        $denied = Http::send('PATCH', $path, ['domain' => 'forbidden.test'], 'crm-domain-denial');
        $proof->same(403, $denied['status'], 'Current write-field policy denies the admitted domain field.');
        $proof->same($owner, crmOwner($db), 'Neither invalid nor unauthorized field changed an owner row.');
        $updated = Http::send('PATCH', $path, ['name' => 'Fleet  updated', 'legal_name' => null], 'crm-update');
        $proof->same(200, $updated['status'], 'Real keyed PATCH commits.');
        $replayed = Http::send('PATCH', $path, ['legal_name' => null, 'name' => 'Fleet  updated'], 'crm-update');
        $proof->same(JsonCanonical::encode($updated['body']['data']), JsonCanonical::encode($replayed['body']['data']), 'Exact keyed replay preserves the semantic projection.');
        $proof->same(true, $replayed['body']['meta']['replayed'], 'The dispatcher identifies the same receipt.');
        $current = crmOwner($db);
        $proof->same($owner['company']['id'], $current['company']['id'], 'PATCH preserves UUID.');
        $proof->same($owner['company']['domain'], $current['company']['domain'], 'Omitted field is not cleared.');
        $proof->same(null, $current['company']['legal_name'], 'Explicit null clears the nullable field.');
        $proof->same($owner['company']['created_by'], $current['company']['created_by'], 'Documented provenance is unchanged.');
        $proof->same($owner['association'], $current['association'], 'PATCH preserves the association.');
        $after += ['protected' => $protected, 'field_denial' => $denied, 'updated' => $updated, 'replayed' => $replayed, 'owner' => $current];

        break;

    case 'foreign':
        $owner = crmOwner($db);
        $path = $base.'/companies/'.$owner['company']['id'];
        foreach (['fixture-crm-a-other-workspace', 'fixture-crm-b'] as $actor) {
            $list = Http::send('GET', $base.'/companies', actor: $actor);
            $show = Http::send('GET', $path, actor: $actor);
            $patch = Http::send('PATCH', $path, ['name' => 'Foreign'], 'crm-foreign', $actor);
            $delete = Http::send('DELETE', $path, key: 'crm-foreign-delete', actor: $actor);
            $proof->same(200, $list['status'], 'The independently authenticated foreign workspace can list its own records.');
            $proof->same([], $list['body']['data']['items'], 'Current workspace excludes A data.');
            foreach ([$show, $patch, $delete] as $response) {
                $proof->check(in_array($response['status'], [403, 404], true), 'Foreign current scope refused the known owner ref.');
            }
            $after['scopes'][$actor] = compact('list', 'show', 'patch', 'delete');
        }
        $proof->same($owner, crmOwner($db), 'Foreign requests leave owner records unchanged.');
        $after['owner'] = $owner;

        break;

    case 'delete':
        $owner = crmOwner($db);
        $before['owner'] = $owner;
        $path = $base.'/companies/'.$owner['company']['id'];
        $db->table('installed_crm_grants')->where(['ability' => 'crm.company.delete', 'field' => 'action'])->delete();
        $denied = Http::send('DELETE', $path, key: 'crm-delete');
        $proof->same(403, $denied['status'], 'Without current ability DELETE is refused.');
        $proof->same($owner, crmOwner($db), 'Denied DELETE changed no record.');
        $db->table('installed_crm_grants')->insert(['workspace_id' => Scenario::W1, 'principal_id' => Scenario::USER_A, 'ability' => 'crm.company.delete', 'field' => 'action']);
        $deleted = Http::send('DELETE', $path, key: 'crm-delete');
        $current = crmOwner($db);
        $proof->same(200, $deleted['status'], 'Authorized DELETE succeeds.');
        $proof->same(['id' => $owner['company']['id'], 'deleted' => true], $deleted['body']['data'], 'Typed delete response carries the original PK.');
        $proof->check($current['company']['deleted_at'] !== null, 'Owner row has a soft-deletion tombstone.');
        $proof->same($owner['association'], $current['association'], 'The association history remains intact.');
        $after += ['denied' => $denied, 'deleted' => $deleted, 'owner' => $current];

        break;

    case 'read-deleted':
        $owner = crmOwner($db);
        $show = Http::send('GET', $base.'/companies/'.$owner['company']['id']);
        $list = Http::send('GET', $base.'/companies');
        $proof->same([404, 200], [$show['status'], $list['status']], 'Fresh public show hides the tombstone and list remains usable.');
        $proof->same([], $list['body']['data']['items'], 'Fresh list releases no deleted owner record.');
        $proof->check($owner['company']['deleted_at'] !== null && $owner['association']['company_id'] === $owner['company']['id'], 'The physical tombstone and link still exist.');
        $after += ['show' => $show, 'list' => $list, 'owner' => $owner];

        break;

    case 'revoke':
        $owner = crmOwner($db);
        $revoked = $db->table('wsp_workspace_members')->where(['workspace_id' => Scenario::W1, 'user_id' => Scenario::USER_A])->update(['status' => 'suspended']);
        $proof->same(1, $revoked, 'The live membership was revoked.');
        $proof->same($owner, crmOwner($db), 'Revocation does not alter the owner history.');
        $after += ['membership' => 'suspended', 'owner' => $owner];

        break;

    case 'denial':
        $owner = crmOwner($db);
        $path = $base.'/companies/'.$owner['company']['id'];
        $responses = [
            'read' => Http::send('GET', $path),
            'update-replay' => Http::send('PATCH', $path, ['name' => 'Fleet  updated', 'legal_name' => null], 'crm-update'),
            'delete-replay' => Http::send('DELETE', $path, key: 'crm-delete'),
        ];
        $proof->same([403, 403, 403], array_values(array_column($responses, 'status')), 'Known operation and business references deny after current membership revoke.');
        $proof->same($owner, crmOwner($db), 'Denied replay does not alter the owner history.');
        $after += ['responses' => $responses, 'owner' => $owner];

        break;

    default:
        throw new LogicException('Unknown CRM phase '.$mode);
}

$after['connections'] = crmConnections();
$proof->report($db, $refs, $before, $after);

function crmOwner(Connection $db): array
{
    return [
        'company' => (array) $db->table('crm_companies')->sole(),
        'contact' => (array) $db->table('crm_contacts')->sole(),
        'association' => (array) $db->table('crm_contact_company')->sole(),
    ];
}

function installCrmGrants(Connection $db, string $workspace, string $actor): void
{
    $db->getSchemaBuilder()->create('installed_crm_grants', static function (Blueprint $table): void {
        $table->uuid('workspace_id');
        $table->uuid('principal_id');
        $table->string('ability');
        $table->string('field');
        $table->primary(['workspace_id', 'principal_id', 'ability', 'field'], 'installed_crm_grants_pk');
    });
    grantCrm($db, $workspace, $actor);
}

function grantCrm(Connection $db, string $workspace, string $actor): void
{
    $grants = [
        'crm.company.create' => ['action'],
        'crm.company.view' => ['action'],
        'crm.company.update' => ['action', '/name', '/legal_name', '/domain'],
        'crm.company.delete' => ['action'],
        'crm.contact.create' => ['action'],
        'crm.contact.view' => ['action'],
        'crm.contact.associate' => ['action'],
    ];
    foreach ($grants as $ability => $fields) {
        foreach ($fields as $field) {
            $db->table('installed_crm_grants')->insert(['workspace_id' => $workspace, 'principal_id' => $actor, 'ability' => $ability, 'field' => $field]);
        }
    }
}

function crmConnections(): array
{
    $relations = [];
    foreach (['control', 'a', 'b', 'budget'] as $role) {
        $db = Scenario::connection($role);
        $relations[$role] = [
            ...Scenario::describe($db),
            'server_version_num' => (int) $db->scalar('SHOW server_version_num'),
            'schema' => $db->scalar('SELECT current_schema()'),
        ];
    }

    return $relations;
}

function productDigest(string $directory): string
{
    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->isLink()) {
            throw new RuntimeException('Product proof cannot digest symlinked files.');
        }

        if ($file->isFile()) {
            $files[substr($file->getPathname(), strlen($directory) + 1)] = hash_file('sha256', $file->getPathname());
        }
    }
    ksort($files);

    return hash('sha256', json_encode($files, JSON_THROW_ON_ERROR));
}
