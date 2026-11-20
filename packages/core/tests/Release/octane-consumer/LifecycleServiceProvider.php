<?php

declare(strict_types=1);

use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Context\Laravel\ScopeControlSchema;
use CoreX\Data\Data\DataScope;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Events\WorkerStopping;

final class LifecycleTrace
{
    public array $events = [];

    public array $requests = [];

    public array $scopeFailures = [];
}

/** Consumer adapter: immutable context resolved from the active request. */
final readonly class RequestTenantResolver implements TenantContextResolver
{
    public function __construct(private string $tenant) {}

    public function current(): TenantContext
    {
        return new TenantContext(account: new AccountRef(id: $this->tenant, slug: $this->tenant, status: 'active', features: [], limits: []));
    }
}

final readonly class LifecycleControlIsolation implements ControlStoreIsolation
{
    public function __construct(private ConnectionInterface $owner) {}

    public function verify(ConnectionInterface $control, string $deploymentId): void
    {
        if ($control !== $this->owner || $deploymentId !== LifecycleServiceProvider::Deployment) {
            throw new ScopeContextMismatch('Unexpected Octane control store.');
        }
    }
}

final readonly class LifecycleBindingVerifier implements PhysicalBindingVerifier
{
    public function __construct(private DatabaseManager $database) {}

    public function verify(PhysicalBinding $binding, TenantContext $context, ConnectionInterface $connection): void
    {
        $owner = LifecycleServiceProvider::Owners[$context->account->id] ?? null;

        if ($owner === null || $binding->bindingId !== $owner || $this->database->connection($owner) !== $connection) {
            throw new ScopeContextMismatch('Unexpected Octane owner connection.');
        }
    }
}

final class LifecyclePrincipalAuthority implements WorkPrincipalAuthority
{
    public function current(TenantContext $context): ScopePrincipal
    {
        return new ScopePrincipal('user', 'fixture-user:'.$context->account->id);
    }

    public function run(DataScope $scope, TenantContext $context, bool $durable, Closure $work): mixed
    {
        $principal = $this->current($context);

        if ($principal->kind !== $scope->principalKind || $principal->id !== $scope->principalId) {
            throw new ScopeContextMismatch('Unexpected Octane principal.');
        }

        return $work();
    }
}

final class LifecycleServiceProvider extends ServiceProvider
{
    public const string Deployment = 'octane-scope-proof';

    public const array Owners = [
        '0198a000-0000-7000-8000-00000000000a' => 'lifecycle-tenant-a',
        '0198a000-0000-7000-8000-00000000000b' => 'lifecycle-tenant-b',
    ];

    public function register(): void
    {
        $this->app['config']->set('corex.scope.deployment_id', self::Deployment);
        $this->app['config']->set('corex.scope.control_connection', 'lifecycle-control');
        foreach (['lifecycle-control', ...array_values(self::Owners)] as $name) {
            $path = $this->app->storagePath($name.'.sqlite');

            if (! is_file($path) && ! touch($path)) {
                throw new RuntimeException('Unable to provision the Octane scope fixture.');
            }
            $this->app['config']->set('database.connections.'.$name, ['driver' => 'sqlite', 'database' => $path, 'prefix' => '']);
        }
        $this->app->scoped(PhysicalBindingVerifier::class, LifecycleBindingVerifier::class);
        $this->app->scoped(WorkPrincipalAuthority::class, LifecyclePrincipalAuthority::class);
        $this->app['config']->set('corex.storage.durable_root', $this->app->storagePath('durable'));
        $this->app['config']->set('corex.storage.ephemeral_root', $this->app->storagePath('ephemeral'));
        $this->app->scoped(TenantContextResolver::class, static fn ($app): RequestTenantResolver => new RequestTenantResolver(
            (string) $app->make(Request::class)->header('X-Tenant', '0198a000-0000-7000-8000-00000000000a'),
        ));
    }

    public function boot(): void
    {
        $this->provisionAuthority();

        foreach ([WorkerStarting::class, RequestReceived::class, RequestHandled::class, RequestTerminated::class, WorkerErrorOccurred::class, WorkerStopping::class] as $event) {
            $this->app['events']->listen($event, static function ($event): void {
                $app = $event->sandbox ?? $event->app;
                $app->make(LifecycleTrace::class)->events[] = $event::class;
            });
        }

        $this->app['router']->get('/lifecycle', static function (Request $request): array {
            $app = app();
            $context = $app->make(TenantContextResolver::class)->current();
            $owner = self::Owners[$context->account->id] ?? throw new ScopeContextMismatch('Unknown Octane account.');
            $database = $app->make(DatabaseManager::class);
            $previous = $database->getDefaultConnection();
            $database->setDefaultConnection($owner);

            try {
                $scope = $app->make(DataScopeFactory::class)->forCurrentPrincipal($context);

                return $app->make(WorkScopeRunner::class)->run($scope, static function (TenantContext $trusted) use ($app, $database, $request, $scope): array {
                    $connection = $database->connection();
                    $app->make(CurrentWorkScope::class)->assert($scope, $connection);
                    $trace = $app->make(LifecycleTrace::class);
                    $disk = $app->make(TenantStorage::class)->disk();
                    $before = $disk->get('marker.txt');
                    $disk->put('marker.txt', $trusted->account->id);
                    $trace->requests[] = [
                        'tenant' => $trusted->account->id,
                        'services' => array_map(static fn (string $key): object => $app->make($key), [CurrentWorkScope::class, TenantStorage::class, Inbox::class, Outbox::class]),
                        'root' => $disk->path(''),
                        'before' => $before,
                        'stored' => $disk->get('marker.txt'),
                        'connection' => $connection->getName(),
                        'binding' => $scope->bindingId,
                        'principal' => $scope->principalId,
                        'marker' => $connection->table('lifecycle_markers')->value('marker'),
                    ];
                    $app['config']->set('lifecycle.leak', 'tenant-a');

                    if ($request->header('X-Fail') === 'yes') {
                        throw new RuntimeException('lifecycle request failed');
                    }

                    return ['tenant' => $trusted->account->id, 'before' => $before];
                });
            } catch (ScopeContextMismatch $exception) {
                $app->make(LifecycleTrace::class)->scopeFailures[] = [$context->account->id, $exception->getMessage()];

                throw $exception;
            } finally {
                $database->setDefaultConnection($previous);
            }
        });
    }

    private function provisionAuthority(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $control = $database->connection('lifecycle-control');
        $this->app->instance(ControlStoreIsolation::class, new LifecycleControlIsolation($control));

        if ($control->getSchemaBuilder()->hasTable('corex_scope_routes')) {
            return;
        }

        ScopeControlSchema::install($control);
        $authority = $this->app->make(DatabaseScopeControl::class);
        foreach (self::Owners as $tenant => $name) {
            $authority->register($tenant, new PhysicalBinding(self::Deployment, $name, 1, 'fixture-incarnation:'.$tenant, hash('sha256', 'fixture-epoch:'.$tenant)));
            $connection = $database->connection($name);
            $connection->getSchemaBuilder()->create('lifecycle_markers', static function (Blueprint $table): void {
                $table->string('marker');
            });
            $connection->table('lifecycle_markers')->insert(['marker' => $tenant]);
        }
    }
}
