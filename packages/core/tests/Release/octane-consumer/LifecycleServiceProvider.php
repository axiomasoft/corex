<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Storage\Contracts\TenantStorage;
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

final class LifecycleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app['config']->set('corex.storage.durable_root', $this->app->storagePath('durable'));
        $this->app['config']->set('corex.storage.ephemeral_root', $this->app->storagePath('ephemeral'));
        $this->app->scoped(TenantContextResolver::class, static fn ($app): RequestTenantResolver => new RequestTenantResolver(
            (string) $app->make(Request::class)->header('X-Tenant', '0198a000-0000-7000-8000-00000000000a'),
        ));
    }

    public function boot(): void
    {
        foreach ([WorkerStarting::class, RequestReceived::class, RequestHandled::class, RequestTerminated::class, WorkerErrorOccurred::class, WorkerStopping::class] as $event) {
            $this->app['events']->listen($event, static function ($event): void {
                $app = $event->sandbox ?? $event->app;
                $app->make(LifecycleTrace::class)->events[] = $event::class;
            });
        }

        $this->app['router']->get('/lifecycle', static function (Request $request): array {
            $app = app();
            $trace = $app->make(LifecycleTrace::class);
            $storage = $app->make(TenantStorage::class);
            $disk = $storage->disk();
            $before = $disk->get('marker.txt');
            $disk->put('marker.txt', (string) $request->header('X-Tenant'));
            $trace->requests[] = [
                'tenant' => $app->make(TenantContextResolver::class)->current()->account->id,
                'services' => array_map(static fn (string $key): object => $app->make($key), [TenantContextRestorer::class, TenantStorage::class, Inbox::class, Outbox::class]),
                'root' => $disk->path(''),
                'before' => $before,
                'stored' => $disk->get('marker.txt'),
            ];
            $app['config']->set('lifecycle.leak', 'tenant-a');

            if ($request->header('X-Fail') === 'yes') {
                throw new RuntimeException('lifecycle request failed');
            }

            return ['tenant' => $request->header('X-Tenant'), 'before' => $before];
        });
    }
}
