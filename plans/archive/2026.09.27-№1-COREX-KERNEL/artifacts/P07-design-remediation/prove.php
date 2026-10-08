<?php

declare(strict_types=1);

// Read-only design counterexamples. No application bootstrap or database connection.

namespace CoreX\Tenancy\Runtime\Contracts {
    use CoreX\Context\Data\DatabaseConnectionConfig;
    use CoreX\Context\Data\TenantContext;

    interface TenantBootstrapper
    {
        public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void;

        public function revert(): void;
    }
}

namespace CoreX\Tenancy\Runtime\Events {
    final readonly class TenantContextInitialized
    {
        public function __construct(public string $accountId) {}
    }
    final readonly class TenantContextEnded
    {
        public function __construct(public string $accountId) {}
    }
}

namespace CoreX\Tenancy\Runtime\Exceptions {
    final class TenantContextUnavailable extends \RuntimeException
    {
        public static function missingAccount(string $id): self
        {
            return new self('missing-account:'.$id);
        }

        public static function runtimePoisoned(): self
        {
            return new self('runtime-poisoned');
        }

        public static function notInitialized(): self
        {
            return new self('not-initialized');
        }

        public static function missingWorkspace(string $id): self
        {
            return new self('missing-workspace:'.$id);
        }

        public static function invalidPayload(): self
        {
            return new self('invalid-payload');
        }
    }
}

namespace {
    require dirname(__DIR__, 4).'/vendor/autoload.php';

    use CoreX\Access\Contracts\Principal;
    use CoreX\Access\Enums\PrincipalType;
    use CoreX\Config\Exceptions\InvalidConfiguration;
    use CoreX\Context\Contracts\AccountConnectionResolver;
    use CoreX\Context\Contracts\TenancyManager;
    use CoreX\Context\Data\AccountRef;
    use CoreX\Context\Data\DatabaseConnectionConfig;
    use CoreX\Context\Data\TenantContext;
    use CoreX\Context\Data\WorkspaceRef;
    use CoreX\Tenancy\Account\Contracts\AccountRead;
    use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
    use CoreX\Tenancy\Runtime\Events\TenantContextInitialized;
    use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
    use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManagerMutant;
    use Illuminate\Cache\CacheManager;
    use Illuminate\Config\Repository;
    use Illuminate\Container\Container;
    use Illuminate\Events\Dispatcher;
    use Illuminate\Filesystem\Filesystem;
    use Illuminate\Queue\Events\JobAttempted;
    use Illuminate\Queue\Events\JobProcessing;
    use Illuminate\Queue\Jobs\SyncJob;
    use Psr\Log\NullLogger;

    $examples = file_get_contents(dirname(__DIR__).'/P07-design/examples.md');
    preg_match('/## 1\. `DatabaseTenancyManager`.*?```php\n(.*?)\n```/s', $examples, $match);

    if (! isset($match[1])) {
        throw new RuntimeException('Manager example not found');
    }
    preg_match('/### 1c\..*?```php\n(.*?)\n```/s', $examples, $diagnostic);
    eval(preg_replace('/^<\?php\s*/', '', $diagnostic[1], 1));
    // Execute exactly the current normative example, never a corrected copy.
    eval(preg_replace('/^<\?php\s*/', '', $match[1], 1));

    final class Accounts implements AccountRead
    {
        public function find(string $accountId, ?string $connectionName = null): ?AccountRef
        {
            return $accountId === 'missing' ? null : account($accountId);
        }

        public function ownerIdentityId(string $accountId, ?string $connectionName = null): ?string
        {
            return null;
        }
    }
    final class Connections implements AccountConnectionResolver
    {
        public function resolve(AccountRef $account): DatabaseConnectionConfig
        {
            if ($account->id === 'bad-resolver') {
                throw new RuntimeException('resolve-failed');
            }

            return new DatabaseConnectionConfig('pgsql', 'unused', 5432, $account->id, 'unused');
        }
    }
    final class Facility implements TenantBootstrapper
    {
        public string $state = 'central';

        public int $reverts = 0;

        public bool $failRevert = false;

        public ?string $failFor = null;

        public function bootstrap(TenantContext $context, DatabaseConnectionConfig $database): void
        {
            $this->state = $context->account->id;

            if ($this->failFor === $this->state) {
                throw new RuntimeException('bootstrap-failed');
            }
        }

        public function revert(): void
        {
            $this->reverts++;
            $this->state = 'central';

            if ($this->failRevert) {
                throw new RuntimeException('revert-failed');
            }
        }
    }
    function account(string $id): AccountRef
    {
        return new AccountRef($id, $id, 'active', [], []);
    }
    function manager(Container $container, array $bootstrappers): DatabaseTenancyManager
    {
        return new DatabaseTenancyManager($bootstrappers, new Accounts, new Connections, $container, new Dispatcher($container), 'central', new NullLogger);
    }

    $checks = [];
    function check(string $name, bool $condition): void
    {
        global $checks;
        $checks[$name] = $condition;

        if (! $condition) {
            throw new RuntimeException('FAILED: '.$name);
        }
    }
    $workspace = new WorkspaceRef('W', 'w', 'team', '/w', null);
    foreach (['missing', 'bad-resolver', 'B'] as $target) {
        $c = new Container;
        $events = new Dispatcher($c);
        $f = new Facility;
        $m = new DatabaseTenancyManager([$f], new Accounts, new Connections, $c, $events, 'central', new NullLogger);
        $m->initialize(account('A'));
        $m->setWorkspace($workspace);

        if ($target === 'B') {
            $f->failFor = 'B';
        }
        $called = false;

        try {
            $m->runFor(account($target), null, static function () use (&$called) {
                $called = true;
            });
        } catch (Throwable $e) {
        }
        check('runFor_restores_'.$target, ! $called && $m->context()?->account->id === 'A' && $m->context()?->workspace === $workspace && $f->state === 'A');
    }
    $c = new Container;
    $events = new Dispatcher($c);
    $f = new Facility;
    $m = new DatabaseTenancyManager([$f], new Accounts, new Connections, $c, $events, 'central', new NullLogger);
    $m->initialize(account('A'));
    $m->setWorkspace($workspace);
    $events->listen(TenantContextInitialized::class, static function ($event) {
        if ($event->accountId === 'B') {
            throw new RuntimeException('event-failed');
        }
    });

    try {
        $m->runFor(account('B'), null, static fn () => null);
    } catch (Throwable $e) {
    }
    check('runFor_restores_event_failure', $m->context()?->account->id === 'A' && $m->context()?->workspace === $workspace && $f->state === 'A');

    $c = new Container;
    $f1 = new Facility;
    $f2 = new Facility;
    $f2->failFor = 'B';
    $m = manager($c, [$f1, $f2]);
    $c->scoped('writer', static fn () => new stdClass);
    $held = $c->make('writer');

    try {
        $m->initialize(account('B'));
    } catch (Throwable $e) {
    }
    check('partial_bootstrap_reverted_and_scope_reset', $f1->state === 'central' && $f2->state === 'central' && $f1->reverts === 1 && $f2->reverts === 1 && ! $m->initialized() && $c->make('writer') !== $held);
    $f1->failRevert = true;
    $f2->failFor = null;
    $m->initialize(account('B'));

    try {
        $m->end();
    } catch (Throwable $e) {
    }

    try {
        $m->initialize(account('C'));
        $poisoned = false;
    } catch (Throwable $e) {
        $poisoned = $e->getMessage() === 'runtime-poisoned';
    }
    check('cleanup_attempts_all_then_poison', $f2->reverts === 2 && $poisoned);

    // Normative mutation, exactly one shared reset call. Original B, mutant A.
    $mutant = str_replace('final class DatabaseTenancyManager ', 'final class DatabaseTenancyManagerMutant ', $match[1]);
    $mutant = str_replace('$this->container->forgetScopedInstances();', '', $mutant, $removed);
    check('scope_mutation_single_match', $removed === 1);
    eval(preg_replace('/^<\?php\s*/', '', $mutant, 1));
    foreach ([DatabaseTenancyManager::class => 'B', DatabaseTenancyManagerMutant::class => 'A'] as $class => $expected) {
        $c = new Container;
        $f = new Facility;
        $m = new $class([$f], new Accounts, new Connections, $c, new Dispatcher($c), 'central', new NullLogger);
        $c->scoped('writer', static fn () => (object) ['database' => $f->state]);
        $m->initialize(account('A'));
        $held = $c->make('writer');
        $inside = $m->runFor(account('B'), null, static fn () => $c->make('writer')->database);
        check('scope_boundary_'.($expected === 'B' ? 'original_passes' : 'mutant_writes_wrong_database'), $inside === $expected);
    }
    // Acyclic eager registrar graph, no workspace bootstrapper dependency.
    $c = new Container;
    foreach (['connection', 'cache', 'session'] as $key) {
        $c->singleton($key, static fn () => new Facility);
    }
    $c->singleton(TenancyManager::class, static fn (Container $c) => manager($c, [$c->make('connection'), $c->make('cache'), $c->make('session')]));
    check('eager_di_acyclic', $c->make(TenancyManager::class) === $c->make(TenancyManager::class));

    // Execute exact cache example against installed Laravel FileStore and locks.
    preg_match('/```php\n(final class CachePrefixBootstrapper.*?)\n```/s', $examples, $cacheMatch);
    eval('use CoreX\Context\Data\TenantContext; use CoreX\Context\Data\DatabaseConnectionConfig; use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper; '.$cacheMatch[1]);
    $directory = sys_get_temp_dir().'/corex-p07-repair-'.bin2hex(random_bytes(8));
    mkdir($directory);

    try {
        $c = new Container;
        $base = ['driver' => 'file', 'path' => $directory.'/data', 'lock_path' => $directory.'/locks'];
        $config = new Repository(['cache' => ['default' => 'file', 'stores' => ['file' => $base]]]);
        $c->instance('config', $config);
        $c->instance('files', new Filesystem);
        $cache = new CacheManager($c);
        $b = new CachePrefixBootstrapper($cache, $config, ['file'], 'tenant_%tenant%_');
        $db = (new Connections)->resolve(account('A'));
        $cache->store()->put('k', 'central', 60);
        $b->bootstrap(new TenantContext(account('A')), $db);
        $cache->store()->put('k', 'A', 60);
        $lockA = $cache->store()->lock('k', 60);
        check('cache_A_lock', $lockA->get());
        $b->revert();
        $b->bootstrap(new TenantContext(account('B')), $db);
        check('cache_B_has_no_A_value', $cache->store()->get('k') === null);
        $lockB = $cache->store()->lock('k', 60);
        check('cache_B_lock_isolated', $lockB->get());
        $lockB->release();
        $b->revert();
        $b->bootstrap(new TenantContext(account('A')), $db);
        check('cache_A_value_restored', $cache->store()->get('k') === 'A');
        check('cache_A_lock_persists', ! $cache->store()->lock('k', 60)->get());
        $lockA->release();
        $b->revert();
        check('cache_central_and_full_config_restored', $cache->store()->get('k') === 'central' && $config->get('cache.stores.file') === $base);
        $config->set('cache.stores.bad', ['driver' => 'array']);

        try {
            (new CachePrefixBootstrapper($cache, $config, ['bad'], 'tenant_%tenant%_'))->bootstrap(new TenantContext(account('A')), $db);
            $denied = false;
        } catch (InvalidConfiguration $e) {
            $denied = true;
        }
        check('unsupported_cache_driver_denied', $denied);
    } finally {
        (new Filesystem)->deleteDirectory($directory);
    }

    preg_match('/```php\n(final class QueueTenantContext.*?)\n```/s', $examples, $queueMatch);
    eval('use CoreX\Context\Contracts\TenancyManager; use CoreX\Tenancy\Account\Contracts\AccountRead; use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable; '.$queueMatch[1]);
    $c = new Container;
    $m = manager($c, [new Facility]);
    $listener = new QueueTenantContext($m, new Accounts, static fn (string $id) => $id === 'W' ? $workspace : null, 'central');
    function syncJob(Container $c, ?array $tenant): SyncJob
    {
        return new SyncJob($c, json_encode(['corex_tenant' => $tenant], JSON_THROW_ON_ERROR), 'sync', null);
    }
    $m->initialize(account('A'));
    $m->setWorkspace($workspace);
    $outer = syncJob($c, ['account' => 'B', 'workspace' => null]);
    $listener->processing(new JobProcessing('sync', $outer));
    check('sync_job_inside_B', $m->context()?->account->id === 'B');
    $inner = syncJob($c, ['account' => 'missing', 'workspace' => null]);

    try {
        $listener->processing(new JobProcessing('sync', $inner));
    } catch (Throwable $e) {
    }
    $listener->finished(new JobAttempted('sync', $inner, $e ?? null));
    check('nested_failure_restores_B', $m->context()?->account->id === 'B');
    $listener->finished(new JobAttempted('sync', $inner));
    check('repeated_completion_does_not_pop_outer', $m->context()?->account->id === 'B');
    $listener->finished(new JobAttempted('sync', $outer));
    check('sync_restores_A_workspace', $m->context()?->account->id === 'A' && $m->context()?->workspace === $workspace);
    $central = syncJob($c, null);
    $listener->processing(new JobProcessing('sync', $central));
    check('sync_central_inside', $m->context() === null);
    $listener->finished(new JobAttempted('sync', $central));
    check('sync_central_restores_A', $m->context()?->workspace === $workspace);

    preg_match('/## 9\..*?```php\n(.*?)\n```/s', $examples, $allowedMatch);
    $allowed = str_replace('#[Override]', '', $allowedMatch[1]);
    eval('use CoreX\Access\Contracts\Principal; use CoreX\Access\Enums\Scope; class AllowedProbe { public function __construct(public object $activity, public object $directGrants, public bool $matrix) {} private function activeRoleIds($p) { return ["role"]; } private function matrixQuery($ids,$entity,$action) { return new class($this->matrix) { public function __construct(private bool $found) {} public function where(...$args) { return $this; } public function exists() { return $this->found; } }; } private function panel() { return "crm"; } '.$allowed.'}');
    $principal = new class implements Principal
    {
        public function principalType(): PrincipalType
        {
            return PrincipalType::User;
        }

        public function principalId(): string
        {
            return 'user';
        }
    };
    $activity = new class
    {
        public bool $active = false;

        public function isActive($key)
        {
            return $this->active;
        }
    };
    $grants = new class
    {
        public bool $granted = true;

        public function hasActive(...$args)
        {
            return $this->granted;
        }
    };
    $probe = new AllowedProbe($activity, $grants, true);
    check('inactive_entity_denies_stale_matrix_and_direct_grant', ! $probe->allowed($principal, 'disabled', 'view'));
    $activity->active = true;
    check('active_entity_matrix_allows', $probe->allowed($principal, 'deals', 'view'));
    $probe->matrix = false;
    check('active_entity_direct_grant_allows', $probe->allowed($principal, 'deals', 'view'));
    $grants->granted = false;
    check('active_entity_without_matrix_or_grant_denies', ! $probe->allowed($principal, 'deals', 'view'));
    echo json_encode(['php' => PHP_VERSION, 'example_sha256' => hash('sha256', $examples), 'checks' => $checks, 'passed' => count($checks), 'database_connections' => 0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
}
