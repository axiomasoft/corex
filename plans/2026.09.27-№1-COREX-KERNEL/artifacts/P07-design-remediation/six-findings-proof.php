<?php

declare(strict_types=1);

// Design harness only. No project bootstrap, network, PostgreSQL or persistent DB.
require dirname(__DIR__, 4).'/vendor/autoload.php';

use CoreX\Audit\Data\AuditEntry;
use CoreX\Audit\Enums\ActorType;
use CoreX\Audit\Internal\AuditLog;
use CoreX\Audit\Internal\QueuedAuditLogger;
use CoreX\Config\Data\CoreOptions;
use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Config\Laravel\CoreOptionsParser;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\ImpersonationState;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Internal\NullImpersonationService;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Wakeup\Contracts\WakeupScheduler;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\CacheManager;
use Illuminate\Cache\MemcachedStore;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Redis\RedisManager;
use Illuminate\Routing\Router;
use Illuminate\Routing\SortedMiddleware;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Str;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;

$root = dirname(__DIR__, 4);
$examples = file_get_contents(dirname(__DIR__).'/P07-design/examples.md');
$checks = [];
function check(string $id, bool $ok): void
{
    global $checks;
    $checks[$id] = $ok;

    if (! $ok) {
        throw new RuntimeException('FAILED '.$id);
    }
}
function block(string $needle): string
{
    global $examples;
    preg_match_all('/```php\n(.*?)\n```/s', $examples, $blocks);
    foreach ($blocks[1] as $code) {
        if (str_contains($code, $needle)) {
            return $code;
        }
    }

    throw new RuntimeException('Missing block '.$needle);
}
function loadBlock(string $needle): void
{
    // Aliases are the documented imports, not a rewritten implementation.
    eval('use CoreX\\Attributes\\Spi; use CoreX\\Attributes\\SpiKind; use CoreX\\Audit\\Data\\AuditEntry; use CoreX\\Audit\\Enums\\ActorType; use CoreX\\Audit\\Internal\\AuditLog; use CoreX\\Data\\Data\\JsonDocument; use CoreX\\Context\\Contracts\\AccountConnectionResolver; use CoreX\\Context\\Contracts\\TenantContextResolver; use CoreX\\Context\\Data\\TenantContext; use CoreX\\Context\\Data\\DatabaseConnectionConfig; use CoreX\\Context\\Data\\AccountRef; use Illuminate\\Config\\Repository; use Illuminate\\Database\\DatabaseManager; use Illuminate\\Database\\ConnectionResolverInterface; '.block($needle));
}
interface TenantBootstrapper
{
    public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void;

    public function revert(): void;
}
final class TenantContextUnavailable extends RuntimeException
{
    public static function missingDatabase(string $id): self
    {
        return new self($id);
    }
}
loadBlock('final readonly class CentralTemplateConnectionResolver');
loadBlock('final class ConnectionBootstrapper');
loadBlock('final class SessionConnectionBootstrapper');
loadBlock('enum ImpersonationProfile');
loadBlock('interface ImpersonationGrantStore');
loadBlock('interface SynchronousAuditWriter');

$app = new Container;
Container::setInstance($app);
$config = new Repository([
    'database' => ['default' => 'central', 'connections' => [
        'central' => ['driver' => 'sqlite', 'database' => 'central', 'prefix' => ''],
        'tenant' => ['driver' => 'sqlite', 'database' => 'A', 'prefix' => ''],
    ]],
    'session' => ['driver' => 'database', 'connection' => 'central', 'table' => 'sessions', 'lifetime' => 120, 'cookie' => 'session', 'encrypt' => false],
]);
$app->instance('config', $config);
$app->instance(Repository::class, $config);
$db = new DatabaseManager($app, new ConnectionFactory($app));
$bank = [];
$db->extend('sqlite', static function ($cfg) use (&$bank) {
    $pdo = $bank[$cfg['database']] ??= new PDO('sqlite::memory:');

    return new SQLiteConnection($pdo, $cfg['database'], '', $cfg);
});
$app->instance('db', $db);
$app->instance(ConnectionResolverInterface::class, $db);
Model::setConnectionResolver($db);
$accounts = $db->connection('central');
$accounts->statement('CREATE TABLE root_accounts(id TEXT PRIMARY KEY, db_name TEXT)');
$accounts->table('root_accounts')->insert([['id' => 'A', 'db_name' => 'tenant_a'], ['id' => 'B', 'db_name' => 'tenant_b']]);
$ref = new AccountRef('A', 'a', 'active', [], []);
$ctx = new TenantContext($ref);

// Finding 1: installed URL parser plus the exact resolver/bootstrap code.
$urlBase = ['driver' => 'pgsql', 'host' => 'wrong', 'username' => 'wrong', 'database' => 'wrong',
    'url' => 'postgres://url_user:url_pass@url_host:5439/central_url?sslmode=require',
    'read' => ['database' => 'central_read'], 'write' => ['database' => 'central_write']];
$config->set('database.connections.central', $urlBase);
$resolver = new CentralTemplateConnectionResolver($db, $config, 'central');
$route = $resolver->resolve($ref);
check('url_resolver_carries_effective_credentials', $route->host === 'url_host' && $route->port === 5439 && $route->username === 'url_user' && $route->options['password'] === 'url_pass');
$b = new ConnectionBootstrapper($db, $config, 'central');
foreach (['A' => 'tenant_a', 'B' => 'tenant_b'] as $id => $database) {
    $r = $resolver->resolve(new AccountRef($id, strtolower($id), 'active', [], []));
    $b->bootstrap($ctx, $r);
    $actual = $db->connection('tenant')->getConfig(); // lazy PG connection; never getPdo()
    check('url_'.$id.'_authoritative_route', $actual['database'] === $database && $actual['host'] === 'url_host' && $actual['sslmode'] === 'require' && array_intersect(['url', 'read', 'write'], array_keys($actual)) === []);
    $b->revert();
}
check('url_central_config_unchanged', $config->get('database.connections.central') === $urlBase);
$b->bootstrap($ctx, new DatabaseConnectionConfig('pgsql', 'cluster_host', 5440, 'cluster_db', 'cluster_user', ['password' => 'cluster_pass']));
check('url_custom_resolver_overrides', $db->connection('tenant')->getConfig('database') === 'cluster_db' && $db->connection('tenant')->getConfig('password') === 'cluster_pass');
$b->revert();
foreach (['url', 'database', 'read', 'write'] as $key) {
    try {
        $b->bootstrap($ctx, new DatabaseConnectionConfig('pgsql', 'h', 5432, 'A', 'u', [$key => 'central']));
        check('route_option_'.$key.'_rejected', false);
    } catch (InvalidConfiguration) {
        check('route_option_'.$key.'_rejected', true);
    } finally {
        $b->revert();
    }
}
$old = (new ConfigurationUrlParser)->parseConfiguration(array_replace($urlBase, ['database' => 'tenant_a']));
check('old_url_counterexample_reproduced', $old['database'] === 'central_url');

// Finding 2: already live database handler actually reads/writes the isolated memory DB.
$config->set('database.connections.central', ['driver' => 'sqlite', 'database' => 'central', 'prefix' => '']);
$config->set('database.connections.tenant', ['driver' => 'sqlite', 'database' => 'A', 'prefix' => '']);
$db->purge('tenant');
foreach (['central', 'tenant'] as $name) {
    $db->connection($name)->statement('CREATE TABLE sessions(id TEXT PRIMARY KEY,user_id TEXT,ip_address TEXT,user_agent TEXT,payload TEXT,last_activity INTEGER)');
}
$cache = new CacheManager($app);
$app->instance('cache', $cache);
$sessions = new SessionManager($app);
$store = $sessions->driver();
$oldHandler = $store->getHandler();
$id = str_repeat('a', 40);
$oldHandler->write($id, serialize(['login_web_hash' => 'central_staff']));
$sb = new SessionConnectionBootstrapper($sessions, $cache, $app, $config, 'central', new Filesystem);
$originalSession = $config->get('session');
$sb->bootstrap($ctx, new DatabaseConnectionConfig('sqlite', '', 0, 'A', ''));
check('live_database_handler_replaced_on_same_store', $sessions->driver() === $store && $store->getHandler() !== $oldHandler);
$store->setId($id);
$store->start();
check('central_cookie_cannot_read_tenant_auth', $store->get('login_web_hash') === null);
// Execute the exact account-binding middleware method in a thin wrapper.
$middleware = block('public function handle(Request $request');
$middleware = str_replace('abort(403);', 'throw new RuntimeException("403");', $middleware); // local HTTP abort seam only
// No altered session operations; Response is Symfony's installed class.
eval('use Illuminate\\Http\\Request; use Symfony\\Component\\HttpFoundation\\Response; class SessionScopeProbe { public function __construct(public object $manager) {} '.$middleware.' }');
$scope = new SessionScopeProbe(new class($ctx)
{
    public function __construct(public TenantContext $ctx) {}

    public function context(): ?TenantContext
    {
        return $this->ctx;
    }
});
$request = Request::create('/');
$request->setLaravelSession($store);
$store->put('login_web_hash', 'legacy_id');
$beforeId = $store->getId();
$scope->handle($request, static fn () => new Response);
check('first_binding_invalidates_auth_rotates_id', $store->get('login_web_hash') === null && $beforeId !== $store->getId() && $store->get('corex.tenancy.account_id') === 'A');
$store->put('login_web_hash', 'tenant_A');
$scope->handle($request, static fn () => new Response);
check('same_account_preserves_auth', $store->get('login_web_hash') === 'tenant_A');
$store->save();
$savedId = $store->getId();
$sb->revert();
check('response_persisted_in_tenant_before_revert', $db->connection('tenant')->table('sessions')->where('id', $savedId)->exists() && ! $accounts->table('sessions')->where('id', $savedId)->exists());
check('session_revert_handler_and_full_config', $store->getHandler() === $oldHandler && $config->get('session') === $originalSession);
$scope->manager->ctx = new TenantContext(new AccountRef('B', 'b', 'active', [], []));

try {
    $scope->handle($request, static fn () => new Response);
    check('mismatch_denies', false);
} catch (RuntimeException $e) {
    check('mismatch_denies_and_clears_auth', $e->getMessage() === '403' && $store->get('login_web_hash') === null);
}

// File handler and cache clone/prefix creation require no daemon: use real Store classes.
$app->instance('files', new Filesystem);
$tmp = sys_get_temp_dir().'/p07-six-'.bin2hex(random_bytes(6));
$config->set('session.driver', 'file');
$config->set('session.files', $tmp);
$fileStore = $sessions->driver('file');
$fileHandler = $fileStore->getHandler();
$sb->bootstrap($ctx, new DatabaseConnectionConfig('sqlite', '', 0, 'A', ''));
$prop = new ReflectionProperty(FileSessionHandler::class, 'path');
$fileStore->getHandler()->write(str_repeat('f', 40), serialize(['tenant' => 'A']));
check('live_file_session_persists_in_created_directory', unserialize($fileStore->getHandler()->read(str_repeat('f', 40))) === ['tenant' => 'A']);
check('live_file_session_gc_with_created_directory', $fileStore->getHandler()->gc(7200) === 0);
check('live_file_session_path_isolated', $prop->getValue($fileStore->getHandler()) === $tmp.'/corex-tenants/'.hash('sha256', 'A'));
$sb->revert();
check('live_file_handler_restored', $fileStore->getHandler() === $fileHandler);
(new Filesystem)->deleteDirectory($tmp);
$app->instance('redis', new RedisManager($app, 'phpredis', []));
$cache->extend('proof-memcached', static function ($app, $cfg) {
    // memcached extension absent on this host; prefix/clone seam only, no daemon calls.
    $store = (new ReflectionClass(MemcachedStore::class))->newInstanceWithoutConstructor();
    $store->setPrefix($cfg['prefix']);

    return new Illuminate\Cache\Repository($store);
});
foreach (['redis', 'memcached'] as $driver) {
    $cfg = ['driver' => $driver === 'redis' ? 'redis' : 'proof-memcached', 'connection' => 'session_redis', 'prefix' => 'central:'];
    $config->set('cache.stores.session_'.$driver, $cfg);
    $config->set('session.driver', $driver);
    $config->set('session.store', 'session_'.$driver);
    $config->set('session.prefix', 'legacy:');
    $config->set('session.connection', 'session_redis');
    $live = $sessions->driver($driver);
    $handler = $live->getHandler();
    $sb->bootstrap($ctx, new DatabaseConnectionConfig('sqlite', '', 0, 'A', ''));
    check('live_'.$driver.'_clone_prefix_isolated', str_starts_with($live->getHandler()->getCache()->getStore()->getPrefix(), 'corex-session:'.hash('sha256', 'A').':'));
    $sb->revert();
    check('live_'.$driver.'_clone_and_config_restored', $live->getHandler() === $handler && $config->get('cache.stores.session_'.$driver) === $cfg && $config->get('session.prefix') === 'legacy:');
}

// Finding 3: exact stock writer implementation through baseline AuditLog, quiet IDs/casts.
$options = CoreOptionsParser::fromArray(['audit' => ['enabled' => false]]);
$app->instance(CoreOptions::class, $options);
foreach (['central', 'tenant'] as $name) {
    $db->connection($name)->statement('CREATE TABLE sys_audit_log(id TEXT PRIMARY KEY NOT NULL,occurred_at TEXT,actor_type TEXT CHECK(actor_type IN (\'support\',\'user\')),actor_id TEXT,workspace_id TEXT,entity_type TEXT,entity_id TEXT,action TEXT,changes TEXT,context TEXT,created_at TEXT)');
}
$accounts->statement('CREATE TABLE root_account_events(id TEXT PRIMARY KEY, account_id TEXT,event TEXT,actor TEXT,payload TEXT,created_at TEXT,updated_at TEXT)');
$contexts = new class($ctx) implements TenantContextResolver
{
    public function __construct(public TenantContext $ctx) {}

    public function current(): TenantContext
    {
        return $this->ctx;
    }
};
$writer = new DatabaseSynchronousAuditWriter($db);
$sink = new DualImpersonationAuditSink($db, $writer, $contexts, static fn () => (string) Str::uuid7(), 'central');
$now = new DateTimeImmutable('2026-10-04T00:00:00Z');
$event = new ImpersonationAuditEvent('impersonation.started', 'A', 'grant', 'staff', 'target', 'support', ImpersonationProfile::Standard, $now, $now->modify('+20 minutes'));
$sink->record($event);
check('stock_writer_durable_despite_audit_disabled_no_worker', $accounts->table('root_account_events')->count() === 1 && $db->connection('tenant')->table('sys_audit_log')->count() === 1 && $db->connection('tenant')->transactionLevel() === 0);
$row = $db->connection('tenant')->table('sys_audit_log')->first();
check('stock_quiet_writer_id_and_correlation', is_string($row->id) && strlen($row->id) === 36 && json_decode($row->context, true)['grant_id'] === 'grant' && $row->actor_type === 'support');
$db->connection('tenant')->beginTransaction();

try {
    $sink->record($event);
    check('ambient_transaction_rejected', false);
} catch (RuntimeException) {
    check('ambient_transaction_rejected_before_central_write', $accounts->table('root_account_events')->count() === 1);
} finally {
    $db->connection('tenant')->rollBack();
}
$db->connection('tenant')->statement('DROP TABLE sys_audit_log');

try {
    $sink->record($event);
    check('tenant_failure_rejected', false);
} catch (QueryException) {
    check('tenant_failure_preserves_central_intent', $accounts->table('root_account_events')->count() === 2);
}
$accounts->statement('DROP TABLE root_account_events');

try {
    $sink->record($event);
    check('central_failure_rejected', false);
} catch (QueryException) {
    check('central_failure_propagated', true);
}
// Baseline logger's killswitch proof, with real class (not a throwing mock).
$imp = new NullImpersonationService;
$clock = new class($now) implements ClockInterface
{
    public function __construct(private DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
};
$logger = new QueuedAuditLogger($options, $imp, $contexts, $clock, 'tenant');
$logger->record(new AuditEntry('impersonation.started', ActorType::Support, occurredAt: $now));
check('actual_queued_logger_disabled_counterexample', true); // dropped audit table would throw if a synchronous write occurred
$app->instance(TenantContextResolver::class, $contexts);
$app->instance(ClockInterface::class, $clock);
$app->scoped(SynchronousAuditWriter::class, static fn () => new DatabaseSynchronousAuditWriter($GLOBALS['db']));
$app->scoped(ImpersonationAuditSink::class, static fn ($c) => new DualImpersonationAuditSink($GLOBALS['db'], $c->make(SynchronousAuditWriter::class), $GLOBALS['contexts'], static fn () => 'id', 'central'));
// Exact new service constructor dependencies cannot be instantiated before product implementation.
// Prove the dangerous DI edge is removed: actual queued logger can depend on a service holding stock sink.
$app->scoped(ImpersonationService::class, static function ($c) {
    $sink = $c->make(ImpersonationAuditSink::class);

    return new class($sink) implements ImpersonationService
    {
        public function __construct(public object $sink) {}

        public function start(string $g, string $s): void {}

        public function stop(): void {}

        public function active(): ?string
        {
            return null;
        }

        public function state(): ?ImpersonationState
        {
            return null;
        }
    };
});
$actualLogger = $app->make(QueuedAuditLogger::class, ['options' => $options, 'tenantContext' => $contexts, 'connection' => 'tenant']);
check('container_sink_writer_queued_logger_graph_acyclic', $actualLogger instanceof QueuedAuditLogger && $app->make(ImpersonationAuditSink::class) instanceof DualImpersonationAuditSink);

// Finding 6: run exact UPDATE with installed Query Builder. PG interval is the sole
// SQLite seam, expressed as equivalent datetime math, not a hand-written approval predicate.
$accounts->statement('CREATE TABLE root_identities(id TEXT PRIMARY KEY,is_staff INTEGER)');
$accounts->table('root_identities')->insert([['id' => 'staff', 'is_staff' => 1], ['id' => 'approver', 'is_staff' => 1], ['id' => 'customer', 'is_staff' => 0]]);
$accounts->statement('CREATE TABLE root_impersonation_grants(id TEXT PRIMARY KEY,staff_identity_id TEXT,account_id TEXT,approved_by TEXT,used_at TEXT,revoked_at TEXT,expires_at TEXT,created_at TEXT,updated_at TEXT,reason TEXT)');
$sqlCode = block('$affected = $central->table');
$sqlCode = str_replace('expires_at <= created_at + make_interval(mins => ?)', "datetime(expires_at) <= datetime(created_at, '+' || ? || ' minutes')", $sqlCode);
foreach (['approver' => true, 'staff' => false, 'customer' => false, 'NULL' => false] as $approver => $allowed) {
    $accounts->table('root_impersonation_grants')->delete();
    $accounts->table('root_impersonation_grants')->insert(['id' => 'g', 'staff_identity_id' => 'staff', 'account_id' => 'A', 'approved_by' => $approver === 'NULL' ? null : $approver, 'created_at' => $now, 'expires_at' => $now->modify('+20 minutes'), 'reason' => 'reason']);
    $c = new GrantClaim('g', 'staff', 'A', ImpersonationProfile::Regulated, $now);
    $central = $accounts;
    eval($sqlCode);
    check('regulated_approver_'.$approver, ($affected === 1) === $allowed);
}
$accounts->table('root_identities')->where('id', 'staff')->update(['is_staff' => 0]);
$accounts->table('root_impersonation_grants')->update(['approved_by' => 'approver', 'used_at' => null]);
eval($sqlCode);
check('regulated_demoted_claimant_denied', $affected === 0);

// Finding 4: extract actual parser function; proposed literal enum extension only.
$gate = file_get_contents($root.'/scripts/package-graph-gate.php');
preg_match('/function parseExtraCorex\(.*?\n}\n/s', $gate, $function);
eval('const COREX_SCHEMA_VERSION=1; const EXIT_CONFIG=2; const VALID_PACKAGE_TYPES=["foundation","capability","integration","product","tooling","fixture"]; const VALID_STABILITY=["stable","experimental","internal","deprecated","unreleased"]; '.$function[0]);
$bridge = ['name' => 'axiomasoft/corex-access-azguard', 'extra' => ['corex' => ['schema-version' => 1, 'package-type' => 'integration', 'stability' => 'unreleased']]];
check('actual_graph_parser_accepts_proposed_enum', $parsed = parseExtraCorex($bridge, 'packages/access-azguard/composer.json') and $parsed['stability'] === 'unreleased');

// Finding 7: derive candidate from installed full SqlDigestBuilder with the exact
// documented nullable signature/constructor guard. Execute nonempty build, not just resolve.
$source = file_get_contents($root.'/packages/notifications/src/Digest/Internal/SqlDigestBuilder.php');
$source = str_replace(['<?php', 'namespace CoreX\\Notifications\\Digest\\Internal;', 'final class SqlDigestBuilder', 'private readonly WakeupScheduler $wakeup,'], ['', '', 'final class CandidateSqlDigestBuilder', 'private readonly ?WakeupScheduler $wakeup,'], $source);
$source = str_replace('    ) {}', "    ) { if (\$enabled && \$wakeup === null) { throw new \\CoreX\\Config\\Exceptions\\InvalidConfiguration(['enabled requires scheduler']); } }", $source);
eval($source);
$connection = $db->connection('tenant');
$connection->statement('CREATE TABLE ntf_digest_runs(id TEXT,recipient_kind TEXT,recipient_id TEXT,period TEXT,period_start TEXT,period_end TEXT,batch_seq INTEGER,membership_hash TEXT,state TEXT,created_at TEXT)');
$connection->statement('CREATE TABLE ntf_digest_items(id TEXT,run_id TEXT,notification_id TEXT,channel TEXT)');
$connection->statement('CREATE TABLE ntf_notifications(id TEXT,recipient_kind TEXT,recipient_id TEXT,cancelled_at TEXT,created_at TEXT)');
$connection->table('ntf_notifications')->insert(['id' => 'notice', 'recipient_kind' => 'user', 'recipient_id' => 'user', 'created_at' => $now]);
$builder = new CandidateSqlDigestBuilder($connection, null, false, $clock);
$window = new DigestWindow($now->modify('-1 hour'), $now->modify('+1 hour'), 'hourly');
$nc = new NotificationContext($ctx, 'user', 'user');
$digest = $builder->build($nc, $window);
check('disabled_nonempty_digest_build_without_scheduler', $digest !== null && $connection->table('ntf_digest_runs')->count() === 1);

try {
    new CandidateSqlDigestBuilder($connection, null, true, $clock);
    check('enabled_null_rejected', false);
} catch (InvalidConfiguration) {
    check('enabled_null_rejected_before_writes', $connection->table('ntf_digest_runs')->count() === 1);
}

// Execute normative DigestBuilder factory with the installed application/container.
class_alias(CandidateSqlDigestBuilder::class, 'SqlDigestBuilder');
class_alias(DigestBuilder::class, 'DigestBuilder');
class_alias(WakeupScheduler::class, 'WakeupScheduler');
$factoryApp = new Application($root);
$factoryApp->instance(DatabaseManager::class, $db);
$factoryApp->instance('NotificationsOptions', (object) ['digestEnabled' => false]);
$factoryApp->instance(ClockInterface::class, $clock);
$oldApp = $app;
$app = $factoryApp;
eval('use Illuminate\\Contracts\\Foundation\\Application; use Illuminate\\Database\\DatabaseManager; use Psr\\Clock\\ClockInterface; '.block('$app->scoped(DigestBuilder::class'));
check('disabled_factory_resolves_without_binding', ! $app->bound('WakeupScheduler') && $app->make('DigestBuilder') instanceof CandidateSqlDigestBuilder);
$app->forgetScopedInstances();
$schedulerResolutions = 0;
$app->bind('WakeupScheduler', static function () use (&$schedulerResolutions) {
    $schedulerResolutions++;

    throw new RuntimeException('must not resolve while disabled');
});
$app->make('DigestBuilder');
check('disabled_factory_never_resolves_spy', $schedulerResolutions === 0);
$app = $oldApp;
Container::setInstance($oldApp);
// Installed router/kernel priority semantics. This verifies the declared ordering,
// not the future HttpRegistrar implementation.
$routeApp = new Application($root);
$router = new Router(new Dispatcher($routeApp), $routeApp);
$kernel = new Kernel($routeApp, $router);
$kernel->addToMiddlewarePriorityBefore(StartSession::class, 'ResolveTenantFromHost');
$kernel->addToMiddlewarePriorityAfter(StartSession::class, 'ScopeSessionToTenant');
$priority = $kernel->getMiddlewarePriority();
$sorted = (new SortedMiddleware($priority, [StartSession::class, ShareErrorsFromSession::class, Authenticate::class, 'ResolveTenantFromHost', 'ScopeSessionToTenant']))->all();
check('installed_route_priority_wraps_start_save_before_auth', $sorted === ['ResolveTenantFromHost', StartSession::class, 'ScopeSessionToTenant', ShareErrorsFromSession::class, Authenticate::class]);
Container::setInstance($oldApp);

$vendorSources = [];
foreach (['vendor/laravel/framework/src/Illuminate/Support/ConfigurationUrlParser.php', 'vendor/laravel/framework/src/Illuminate/Session/SessionManager.php', 'vendor/laravel/framework/src/Illuminate/Session/Middleware/StartSession.php', 'packages/core/src/Audit/Internal/QueuedAuditLogger.php', 'packages/core/src/Audit/Internal/AuditLog.php', 'scripts/package-graph-gate.php', 'packages/notifications/src/Digest/Internal/SqlDigestBuilder.php'] as $path) {
    $vendorSources[$path] = hash_file('sha256', $root.'/'.$path);
}
echo json_encode(['php' => PHP_VERSION, 'example_sha256' => hash('sha256', $examples), 'checks' => $checks, 'passed' => count($checks), 'sources' => $vendorSources, 'database' => 'isolated PDO sqlite::memory: only', 'project_db_connections' => 0, 'limitations' => ['No PG interval/commit failure/concurrent claim test', 'No Redis/memcached daemon operations; Memcached extension absent, store allocated without constructor for clone/prefix seam', 'HTTP middleware method executed; full provider/request pipeline remains future product acceptance', 'Candidate digest uses existing wakeup namespace as a type seam; absence of scheduler binding is tested separately']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
