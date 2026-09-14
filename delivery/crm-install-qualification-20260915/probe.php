<?php

declare(strict_types=1);

use CoreX\Api\ApiController;
use CoreX\Api\Contracts\OperationAuthorizer;
use CoreX\Api\Contracts\Principal;
use CoreX\Api\Contracts\PrincipalResolver;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\ErrorBoundary;
use CoreX\Api\OpenApi;
use CoreX\Api\Operation;
use CoreX\Modules\Contracts\ModuleLifecycle;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Enums\DisableReason;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\AccountConnectionResolver;
use CoreX\Tenancy\Contracts\PrincipalWorkspaceResolver;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\Models\Account;
use CoreX\Tenancy\Provisioning\ClusterAccountConnectionResolver;
use Flex\Crm\Actions\ConvertLead;
use Flex\Crm\Actions\CreateCompany;
use Flex\Crm\Actions\CreateLead;
use Flex\Crm\Contracts\CrmExecutionContext;
use Flex\Crm\Data\Actor;
use Flex\Crm\Data\CompanyData;
use Flex\Crm\Data\ExecutionContext;
use Flex\Crm\Events\CompanyCreated;
use Flex\Crm\Models\Company;
use Flex\Crm\Tests\Support\StorageCatalogue;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\PackageManifest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/StorageCatalogue.php'; // Explicit independent test helper, never production autoload.

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

final class ProbePrincipal implements Principal, PrincipalResolver
{
    public ?string $userId = null;

    public function id(): string
    {
        return $this->userId ?? throw new AuthorizationException('Authentication required.');
    }

    public function resolve(Request $request): ?Principal
    {
        return $this->userId === null ? null : $this;
    }
}

final class ProbePolicy implements OperationAuthorizer
{
    public bool $allow = true;

    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $this->allow && str_starts_with($operation->ability, 'crm.');
    }
}

// Test-only product adapter: authoritative account context + trusted principal.
// No replacement tenancy engine, permissive registry or physical-context stub.
final class ProbeExecution implements CrmExecutionContext
{
    public ?string $actorOverride = null;

    public string $connection = 'tenant';

    public function __construct(private TenantContextResolver $tenants, private ProbePrincipal $principal) {}

    public function current(): ExecutionContext
    {
        $tenant = $this->tenants->current();
        $id = $this->principal->id();
        $user = DB::connection('tenant')->table('users')->where('id', $id)->first();

        return new ExecutionContext(tenant: $tenant, connectionName: $this->connection,
            actor: new Actor(source: 'user', id: $this->actorOverride ?? $id, name: $user?->name ?? ''), principalId: $id);
    }
}

$prefix = getenv('Q_PREFIX');
$adminDatabase = getenv('Q_ADMIN_DATABASE');
check(is_string($prefix) && preg_match('/^corex_crm_q[a-z0-9_]+$/D', $prefix) === 1, 'Explicit qualification prefix required.');
check(is_string($adminDatabase) && str_ends_with($adminDatabase, '_test'), 'Isolated administration target required.');
$admin = new PDO('pgsql:host=127.0.0.1;port=5434;dbname='.$adminDatabase, 'corex', 'corex', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$targets = [$prefix.'_root_test', $prefix.'_account_a_test', $prefix.'_account_b_test'];
foreach ($targets as $database) {
    check(strlen($database) <= 63 && str_ends_with($database, '_test'), 'Unsafe database target.');
    $query = $admin->prepare('SELECT count(*) FROM pg_database WHERE datname=?');
    $query->execute([$database]);
    check((int) $query->fetchColumn() === 0, 'Never reuse or drop an existing qualification database: '.$database);
    $admin->exec('CREATE DATABASE "'.$database.'"');
}
$app = Application::configure(basePath: __DIR__)
    ->withRouting(using: static function (): void {
        Route::match(['GET', 'POST'], '/chosen/{version}/{path}', ApiController::class)
            ->where('path', '.*')->middleware(ErrorBoundary::class);
    })->create();
$app->make(PackageManifest::class)->build(); // Explicit discovery after --no-scripts.
$console = $app->make(ConsoleKernel::class);
$console->bootstrap();
$evidence = ['targets' => $targets, 'profile' => 'PostgreSQL >=15 / uuid7 / PHP >=8.4 / sealed Stancl', 'exchanges' => []];
check((int) DB::connection('pgsql')->selectOne("SELECT count(*) AS n FROM information_schema.tables WHERE table_schema='public'")->n === 0, 'Registration ran DDL.');
check(! class_exists('Flex\\Crm\\Tests\\TestCase'), 'Production autoload exposes package tests.');
foreach ([ModuleRegistry::class, ModuleLifecycle::class, TenancyManager::class, TenantContextResolver::class] as $contract) {
    $evidence['contracts'][$contract] = $app->make($contract)::class;
}
foreach ([CoreX\Api\Dispatcher::class, CoreX\Modules\Lifecycle\DatabaseModuleLifecycle::class, CoreX\Tenancy\StanclTenancyManager::class, CreateCompany::class] as $class) {
    $path = (new ReflectionClass($class))->getFileName();
    check(str_starts_with(realpath($path), realpath(__DIR__.'/vendor').'/'), 'Source outside installed vendor.');
    $evidence['autoload'][$class] = ['path' => substr(realpath($path), strlen(realpath(__DIR__)) + 1), 'sha256' => hash_file('sha256', $path)];
}
function migrateInstalled(ConsoleKernel $console, string $database, string $path): void
{
    check($console->call('migrate', ['--database' => $database, '--path' => realpath($path), '--realpath' => true, '--force' => true]) === 0, 'Owning migration failed: '.$path);
}
migrateInstalled($console, 'pgsql', __DIR__.'/vendor/corex/tenancy/database/migrations/root');
check($console->call('corex:modules:compile') === 0, 'Installed compile failed.');
$principal = new ProbePrincipal;
$policy = new ProbePolicy;
$app->instance(PrincipalResolver::class, $principal);
$app->instance(OperationAuthorizer::class, $policy);
$execution = new ProbeExecution($app->make(TenantContextResolver::class), $principal);
$app->instance(CrmExecutionContext::class, $execution);
$app->instance(AccountConnectionResolver::class, new ClusterAccountConnectionResolver('pgsql'));
$evidence['contracts'][AccountConnectionResolver::class] = $app->make(AccountConnectionResolver::class)::class;
$evidence['contracts'][PrincipalWorkspaceResolver::class] = $app->make(PrincipalWorkspaceResolver::class)::class;
$manager = $app->make(TenancyManager::class);
$registry = $app->make(ModuleRegistry::class);
$lifecycle = $app->make(ModuleLifecycle::class);
$cluster = (string) Str::uuid7();
DB::connection('pgsql')->table('root_clusters')->insert(['id' => $cluster, 'name' => $prefix, 'pg_host_writer' => '127.0.0.1', 'pg_port' => 5434, 'pgbouncer_host' => '127.0.0.1', 'pgbouncer_port' => 5434, 'app_db_user' => 'corex']);
$accounts = [];
foreach (['a', 'b'] as $offset => $name) {
    $user = (string) Str::uuid7();
    DB::connection('pgsql')->table('root_identities')->insert(['id' => $user, 'email' => $user.'@example.test', 'name' => 'Trusted '.$name]);
    $account = Account::on('pgsql')->create(['cluster_id' => $cluster, 'db_name' => $targets[$offset + 1], 'slug' => 'account-'.$name, 'template_version' => 1, 'embedding_model' => 'bge-m3', 'embedding_dim' => 1024, 'owner_identity_id' => $user, 'status' => 'active']);
    $ref = new AccountRef(id: $account->id, slug: $account->slug, status: 'active', features: [], limits: []);
    $manager->initialize($ref);
    check(! $registry->isActive('flex/crm', $manager->context()), 'Default registration enabled CRM.');
    migrateInstalled($console, 'tenant', __DIR__.'/vendor/corex/core/database/migrations/pg');
    migrateInstalled($console, 'tenant', __DIR__.'/vendor/corex/tenancy/database/migrations/tenant');
    migrateInstalled($console, 'tenant', __DIR__.'/vendor/corex/modules/database/migrations');
    $workspace = (string) Str::uuid7();
    $other = (string) Str::uuid7();
    DB::connection('tenant')->table('users')->insert(['id' => $user, 'email' => $user.'@example.test', 'name' => 'Trusted '.$name]);
    foreach ([$workspace => 'main', $other => 'other'] as $id => $slug) {
        DB::connection('tenant')->table('wsp_workspaces')->insert(['id' => $id, 'name' => $slug, 'slug' => $slug, 'path' => str_replace('-', '', $id)]);
        DB::connection('tenant')->table('wsp_workspace_members')->insert(['id' => (string) Str::uuid7(), 'workspace_id' => $id, 'user_id' => $user, 'status' => 'active']);
    }
    $principal->userId = $user;
    $manager->setWorkspace($app->make(PrincipalWorkspaceResolver::class)->resolveFor($user, 'main'));
    $lifecycle->enable('flex/crm');
    check($registry->isActive('flex/crm', $manager->context()), 'Real lifecycle did not enable CRM.');
    $catalogue = StorageCatalogue::read(DB::connection('tenant'), 'public');
    DB::connection('tenant')->transaction(function () use ($catalogue): void {
        DB::connection('tenant')->statement('CREATE SCHEMA crm_reference_test');
        DB::connection('tenant')->statement('SET LOCAL search_path TO crm_reference_test,public');
        DB::connection('tenant')->unprepared(file_get_contents(__DIR__.'/approved-storage.sql'));
        check($catalogue === StorageCatalogue::read(DB::connection('tenant'), 'crm_reference_test'), 'Full installed schema differs from approved DDL.');
        DB::connection('tenant')->statement('DROP SCHEMA crm_reference_test CASCADE');
    });
    $evidence['catalogue'] = $catalogue;
    $accounts[$name] = compact('ref', 'user', 'workspace', 'other');
    $manager->end();
}
$kernel = $app->make(HttpKernel::class);
$request = function (string $method, string $path, array $input, int $status, string $schemaPath) use ($kernel, &$evidence): array {
    $req = Request::create('/chosen/v1/'.$path, $method, parameters: $method === 'GET' ? $input : [],
        server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: $method === 'GET' ? null : json_encode($input, JSON_THROW_ON_ERROR));
    $response = $kernel->handle($req);
    check($response->getStatusCode() === $status, $path.' expected '.$status.' got '.$response->getStatusCode().': '.$response->getContent());
    $payload = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $evidence['exchanges'][] = ['method' => $method, 'path' => $path, 'schema_path' => $schemaPath, 'input' => (object) $input, 'status' => $status, 'response' => $payload];
    $kernel->terminate($req, $response);

    return $payload['data'] ?? [];
};
$request('GET', 'companies', [], 403, '/companies'); // missing central context
$principal->userId = null;
$request('GET', 'companies', [], 401, '/companies');
$ids = [];
foreach (['a', 'b', 'a'] as $name) {
    $account = $accounts[$name];
    $manager->initialize($account['ref']);
    $principal->userId = $account['user'];
    $manager->setWorkspace($app->make(PrincipalWorkspaceResolver::class)->resolveFor($principal->id(), 'main'));
    $db = DB::connection('tenant');
    $expected = $app->make(AccountConnectionResolver::class)->resolve($manager->context()->account);
    $actual = $db->selectOne('SELECT current_database() AS database,current_user AS username,pg_backend_pid() AS backend');
    check($actual->database === $expected->database && $actual->username === $expected->username, 'PDO/context mismatch.');
    $evidence['physical_sequence'][] = ['account' => $name, 'context_account' => $manager->context()->account->id, 'workspace' => $manager->context()->workspace->id, 'config' => array_intersect_key($db->getConfig(), array_flip(['driver', 'host', 'port', 'database', 'username'])), 'pdo' => (array) $actual];
    if (! isset($ids[$name])) {
        $company = $request('POST', 'companies', ['name' => 'Same title', 'phones' => [['value' => '+12025550123', 'is_primary' => true]]], 201, '/companies');
        $contact = $request('POST', 'contacts', ['name_first' => 'Jane', 'emails' => [['value' => 'JANE@example.test']]], 201, '/contacts');
        $request('POST', 'contacts/'.$contact['id'].'/companies', ['company_id' => $company['id'], 'label' => 'Buyer', 'is_primary' => true], 201, '/contacts/{contactId}/companies');
        $request('POST', 'contacts/'.$contact['id'].'/companies', ['company_id' => $company['id']], 422, '/contacts/{contactId}/companies');
        $ids[$name] = ['company' => $company['id'], 'contact' => $contact['id']];
    }
    $listed = $request('GET', 'companies', ['limit' => '10'], 200, '/companies');
    check(count($listed['items']) === 1 && $listed['items'][0]['id'] === $ids[$name]['company'], 'Account read bleed.');
    $request('GET', 'companies/'.$ids[$name]['company'], [], 200, '/companies/{companyId}');
    $request('GET', 'contacts', [], 200, '/contacts');
    $request('GET', 'contacts/'.$ids[$name]['contact'], [], 200, '/contacts/{contactId}');
    if (isset($ids[$name === 'a' ? 'b' : 'a'])) {
        $request('GET', 'companies/'.$ids[$name === 'a' ? 'b' : 'a']['company'], [], 404, '/companies/{companyId}');
    }
    $manager->end();
}
$a = $accounts['a'];
$manager->initialize($a['ref']);
$principal->userId = $a['user'];
$manager->setWorkspace($app->make(PrincipalWorkspaceResolver::class)->resolveFor($a['user'], 'main'));
$request('POST', 'companies', ['name' => 'Forged actor', 'created_by' => ['source' => 'system']], 422, '/companies');
$request('POST', 'companies', ['name' => 'Forged conn', 'connection' => 'pgsql'], 422, '/companies');
$request('POST', 'companies', ['name' => 'Foreign owner', 'owner_id' => $accounts['b']['user']], 403, '/companies');
$request('POST', 'companies', ['name' => 'Foreign source', 'source_id' => (string) Str::uuid7()], 403, '/companies');
$request('POST', 'companies', ['name' => 'Numeric boolean', 'account_level' => 1], 422, '/companies');
$request('POST', 'companies', ['name' => 'No null capability', 'account_level' => true], 403, '/companies');
$execution->actorOverride = $accounts['b']['user'];
$request('GET', 'companies', [], 403, '/companies');
$execution->actorOverride = null;
$execution->connection = 'pgsql';
$request('GET', 'companies', [], 403, '/companies');
$execution->connection = 'tenant';
$policy->allow = false;
$request('GET', 'companies', [], 403, '/companies');
$policy->allow = true;
$db = DB::connection('tenant');
$db->table('wsp_workspace_members')->where('workspace_id', $a['workspace'])->where('user_id', $a['user'])->update(['status' => 'suspended']);
$request('GET', 'companies', [], 403, '/companies');
$db->table('wsp_workspace_members')->where('workspace_id', $a['workspace'])->update(['status' => 'active']);
$db->table('wsp_workspaces')->where('id', $a['workspace'])->update(['deleted_at' => now()]);
$request('GET', 'companies', [], 403, '/companies');
$db->table('wsp_workspaces')->where('id', $a['workspace'])->update(['deleted_at' => null]);
$db->table('users')->where('id', $a['user'])->update(['is_active' => false]);
$request('GET', 'companies', [], 403, '/companies');
$db->table('users')->where('id', $a['user'])->update(['is_active' => true]);
$manager->setWorkspace($app->make(PrincipalWorkspaceResolver::class)->resolveFor($a['user'], 'other'));
check($request('GET', 'companies', [], 200, '/companies')['items'] === [], 'Workspace read bleed.');
$request('GET', 'companies/'.$ids['a']['company'], [], 404, '/companies/{companyId}');
$manager->setWorkspace(null);
$request('GET', 'companies', [], 403, '/companies');
$manager->setWorkspace($app->make(PrincipalWorkspaceResolver::class)->resolveFor($a['user'], 'main'));
$events = 0;
$app['events']->listen(CompanyCreated::class, function () use (&$events): void { $events++; });
$before = $db->table('crm_companies')->count();
$db->beginTransaction();
app(CreateCompany::class)->handle(new CompanyData(name: 'Rollback'));
check($events === 0, 'Success event dispatched before commit.');
$db->rollBack();
check($db->table('crm_companies')->count() === $before && $events === 0, 'Rollback lost row/event invariant.');
$db->beginTransaction();
app(CreateCompany::class)->handle(new CompanyData(name: 'Committed'));
check($events === 0, 'Success event dispatched inside outer transaction.');
$db->commit();
check($events === 1, 'Success event not dispatched after commit.');
$lead = app(CreateLead::class)->handle(title: 'Explicit conversion', contactName: 'Mary');
app(ConvertLead::class)->handle($lead);
check($lead->fresh()->status->value === 'converted' && $lead->fresh()->created_by['id'] === $a['user'], 'Canonical sales/provenance mismatch.');
$snapshot = StorageCatalogue::read($db, 'public');
$counts = [];
foreach (array_keys($snapshot) as $table) { $counts[$table] = $db->table($table)->count(); }
$lifecycle->disable('flex/crm', DisableReason::Manual);
$request('GET', 'companies', [], 404, '/companies'); // warm disabled registry
$lifecycle->enable('flex/crm');
$lifecycle->enable('flex/crm');
check(StorageCatalogue::read($db, 'public') === $snapshot, 'Re-enable changed schema.');
foreach ($counts as $table => $count) { check($db->table($table)->count() === $count, 'Disable/re-enable changed rows: '.$table); }
$evidence['lifecycle'] = ['disabled_after_warm' => 404, 'preserved_rows' => $counts, 'repeated_enable' => 'idempotent', 'rollback_event_count' => 0, 'after_commit_event_count' => $events];
$manager->end();
DB::connection('pgsql')->table('root_accounts')->where('id', $a['ref']->id)->update(['status' => 'suspended']);
$manager->initialize($a['ref']);
$request('GET', 'companies', [], 403, '/companies');
$manager->end();
$document = $app->make(OpenApi::class)->document('v1', 'https://example.test/chosen/v1');
check(count(array_merge(...array_map('array_values', (array) $document['paths']))) === 7, 'Changed seven-operation surface.');
file_put_contents(__DIR__.'/openapi.json', json_encode($document, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$evidence['openapi_sha256'] = hash_file('sha256', __DIR__.'/openapi.json');
$evidence['application_key_sha256'] = hash('sha256', config('app.key'));
$evidence['php'] = PHP_VERSION;
$evidence['laravel'] = Application::VERSION;
$evidence['database_version'] = DB::connection('pgsql')->selectOne('SELECT version() AS version')->version;
file_put_contents(__DIR__.'/evidence.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Installed physical CRM qualification passed.'.PHP_EOL;
