<?php

// Design-only: execute normative Markdown; no DB access. Reuse installed Laravel and prior harness classes.
ob_start();
require __DIR__.'/prove.php';
ob_end_clean();
$checks = [];
check('queue_constructor_exact_arity', (new ReflectionClass(QueueTenantContext::class))->getConstructor()->getNumberOfParameters() === 4);
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\DatabaseConnectionConfig;
use CoreX\Context\Data\TenantContext;
use CoreX\Tenancy\Account\Contracts\AccountRead;
use CoreX\Tenancy\Laravel\Http\Middleware\AccountStatusGateDesign;
use CoreX\Tenancy\Laravel\Support\StatusGatePolicy;
use CoreX\Tenancy\Runtime\Contracts\TenantBootstrapper;
use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use CoreX\Tenancy\Runtime\Internal\TenantCleanupFailure;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Events\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Psr\Log\AbstractLogger;
use Symfony\Component\HttpKernel\Exception\HttpException;

foreach (['initialize', 'runFor-central', 'runFor-outer', 'logger-fails', 'callback-and-cleanup', 'callback-and-cleanup-outer'] as $scenario) {
    $c = new Container;
    $f1 = new Facility;
    $f2 = new Facility;
    $logger = new class extends AbstractLogger
    {
        public array $entries = [];

        public bool $fails = false;

        public function log($level, string|Stringable $message, array $context = []): void
        {
            $this->entries[] = [$level, $message, $context];

            if ($this->fails) {
                throw new RuntimeException('log-failed');
            }
        }
    };
    $m = new DatabaseTenancyManager([$f1, $f2], new Accounts, new Connections, $c, new Dispatcher($c), 'central', $logger);

    if (in_array($scenario, ['runFor-outer', 'callback-and-cleanup-outer'], true)) {
        $m->initialize(account('A'));
    }
    $f2->failFor = str_starts_with($scenario, 'callback-and-cleanup') ? null : 'B';
    // For outer context do not fail its initial end; fail only after B mutates.
    $bootstrapper = new class($f1, $f2) implements TenantBootstrapper
    {
        public function __construct(private Facility $f1, private Facility $f2) {}

        public function bootstrap(TenantContext $context, DatabaseConnectionConfig $db): void
        {
            $this->f1->failRevert = $this->f2->failRevert = true;
        }

        public function revert(): void {}
    };
    // Include this before the failing facility so bootstrap failure has failing revert causes.
    $m = new DatabaseTenancyManager([$f1, $bootstrapper, $f2], new Accounts, new Connections, $c, new Dispatcher($c), 'central', $logger);

    if (in_array($scenario, ['runFor-outer', 'callback-and-cleanup-outer'], true)) {
        $m->initialize(account('A'));
        $f1->failRevert = $f2->failRevert = false;
    }
    $logger->fails = $scenario === 'logger-fails';
    $before1 = $f1->reverts;
    $before2 = $f2->reverts;

    try {
        if ($scenario === 'initialize' || $scenario === 'logger-fails') {
            $m->initialize(account('B'));
        } else {
            $m->runFor(account('B'), null, static function () use ($scenario) {
                if (str_starts_with($scenario, 'callback-and-cleanup')) {
                    if ($scenario === 'callback-and-cleanup-outer') {
                        global $checks;
                        check('outer_callback_reached', true);
                    }

throw new RuntimeException('callback-failed');
                }
            });
        }
        check($scenario.'_throws', false);
    } catch (TenantCleanupFailure $e) {
        $expected = str_starts_with($scenario, 'callback-and-cleanup') ? 'callback-failed' : 'bootstrap-failed';
        check($scenario.'_causes', $e->getPrevious()?->getMessage() === $expected && $e->cleanup->getMessage() === 'revert-failed');
        $entry = end($logger->entries);
        check($scenario.'_logs_exact_causes', $entry[0] === 'error' && $entry[2]['exception'] === $e->cleanup && $entry[2]['original_exception'] === $e->getPrevious());
        check($scenario.'_all_reverts', $f1->reverts > $before1 && $f2->reverts > $before2);
    }

    try {
        $m->context();
        check($scenario.'_poisoned', false);
    } catch (Throwable $e) {
        check($scenario.'_poisoned', $e->getMessage() === 'runtime-poisoned');
    }
}

preg_match('/### 6c\..*?```php\n(.*?)\n```/s', $examples, $code);
// Rename class only to coexist with installed baseline; its method bodies are unmodified.
$code = str_replace('final class AccountStatusGate', 'final class AccountStatusGateDesign', $code[1]);
eval(preg_replace('/^<\?php\s*/', '', $code, 1));
$app = new Application(dirname(__DIR__, 4));
Container::setInstance($app);
$contexts = new class implements TenantContextResolver
{
    public TenantContext $ctx;

    public function current(): TenantContext
    {
        return $this->ctx;
    }
};
$accounts = new class implements AccountRead
{
    public ?string $owner = 'owner';

    public array $reads = [];

    public function find(string $id, ?string $connectionName = null): ?AccountRef
    {
        return null;
    }

    public function ownerIdentityId(string $id, ?string $connectionName = null): ?string
    {
        $this->reads[] = [$id, $connectionName];

        return $this->owner;
    }
};
$auth = new class implements Factory
{
    public object $g;

    public function __construct()
    {
        $this->g = new class implements Guard
        {
            public ?string $identity = 'owner';

            public function id()
            {
                return $this->identity;
            }

            public function check()
            {
                return $this->identity !== null;
            }

            public function guest()
            {
                return ! $this->check();
            }

            public function user()
            {
                return null;
            }

            public function validate(array $credentials = [])
            {
                return false;
            }

            public function hasUser()
            {
                return $this->check();
            }

            public function setUser(Authenticatable $user)
            {
                return $this;
            }
        };
    }

    public function guard($name = null)
    {
        return $this->g;
    }

    public function shouldUse($name) {}
};
$policy = new StatusGatePolicy('billing', 'export', ['GET', 'HEAD', 'OPTIONS']);
$gate = new AccountStatusGateDesign($policy, $contexts, $accounts, $auth, 'explicit-central');
$rows = [
    ['trial', 'other', 'owner', 'POST', 'other', null, 200], ['active', null, null, 'POST', 'other', null, 200],
    ['suspended', 'owner', 'owner', 'POST', 'billing', null, 200], ['suspended', 'owner', 'owner', 'GET', 'else', 'billing', 200],
    ['suspended', 'owner', 'owner', 'GET', 'other', null, 403], ['suspended', 'other', 'owner', 'GET', 'billing', null, 403],
    ['suspended', 'owner', null, 'GET', 'billing', null, 403], ['suspended', null, null, 'GET', 'billing', null, 403],
    ['grace', 'owner', 'owner', 'GET', 'other', null, 200], ['grace', 'owner', 'owner', 'HEAD', 'other', null, 200],
    ['grace', 'owner', 'owner', 'POST', 'export', null, 200], ['grace', 'owner', 'owner', 'POST', 'else', 'export', 200],
    ['grace', 'owner', 'owner', 'POST', 'other', null, 403], ['grace', 'other', 'owner', 'GET', 'other', null, 403],
    ['grace', 'owner', null, 'GET', 'other', null, 403], ['grace', null, null, 'GET', 'other', null, 403],
    ['exporting', 'owner', 'owner', 'GET', 'other', null, 410], ['deleted', 'owner', 'owner', 'GET', 'other', null, 410],
    ['pending', 'owner', 'owner', 'GET', 'other', null, 403], ['provisioning', 'owner', 'owner', 'GET', 'other', null, 403], ['bogus', 'owner', 'owner', 'GET', 'other', null, 403],
];
foreach ($rows as $i => [$status,$user,$owner,$method,$path,$name,$expected]) {
    $contexts->ctx = new TenantContext(new AccountRef('A', 'a', $status, [], []));
    $accounts->owner = $owner;
    $auth->g->identity = $user;
    $request = Request::create('/'.$path, $method);

    if ($name !== null) {
        $route = new Route($method, $path, static fn () => null);
        $route->name($name);
        $request->setRouteResolver(static fn () => $route);
    }

    try {
        $result = $gate->handle($request, static fn () => new Response('', 200));
        $actual = $result->getStatusCode();
    } catch (HttpException $e) {
        $actual = $e->getStatusCode();
    }
    check('status_matrix_'.$i, $actual === $expected);
}
check('owner_reads_explicit_central', count($accounts->reads) > 0 && array_all($accounts->reads, static fn ($read) => $read === ['A', 'explicit-central']));
// Execute the exact middleware body in a typed manager wrapper; exercise the null branch.
preg_match('/## 6\. `ScopeSessionToTenant`.*?```php\n(.*?)\n```/s', $examples, $scope);
eval('use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response; use CoreX\Tenancy\Runtime\Exceptions\TenantContextUnavailable; final class SessionScopeNullProbe { public function __construct(private CoreX\Context\Contracts\TenancyManager $manager) {} '.$scope[1].'}');
$m = manager(new Container, []);

try {
    (new SessionScopeNullProbe($m))->handle(Request::create('/'), static fn () => new Symfony\Component\HttpFoundation\Response);
    check('null_context_domain_error', false);
} catch (TenantContextUnavailable $e) {
    check('null_context_domain_error', $e->getMessage() === 'not-initialized');
}
echo json_encode(['example_sha256' => hash('sha256',$examples), 'passed' => count($checks), 'checks' => $checks, 'database_connections' => 0, 'limits' => 'Exact Markdown manager/status/scope bodies; fake read/auth ports, actual Laravel Request/Route/Application/abort. Full provider boot and PostgreSQL deferred to product execution.'],JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
