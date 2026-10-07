<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Contracts\Outbox;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Contracts\Client;
use Laravel\Octane\Events\RequestHandled;
use Laravel\Octane\Events\RequestReceived;
use Laravel\Octane\Events\RequestTerminated;
use Laravel\Octane\Events\WorkerErrorOccurred;
use Laravel\Octane\Events\WorkerStarting;
use Laravel\Octane\Events\WorkerStopping;
use Laravel\Octane\OctaneResponse;
use Laravel\Octane\RequestContext;
use Laravel\Octane\Worker;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/octane-consumer/LifecycleServiceProvider.php';

final class LifecycleClient implements Client
{
    public array $statuses = [];

    public array $errors = [];

    public bool $failResponse = false;

    public function marshalRequest(RequestContext $context): array
    {
        throw new LogicException('Requests are supplied directly to the real Worker.');
    }

    public function respond(RequestContext $context, OctaneResponse $response): void
    {
        if ($this->failResponse) {
            $this->failResponse = false;

            throw new RuntimeException('fixture transport failure');
        }

        $this->statuses[] = $response->response->getStatusCode();
    }

    public function error(Throwable $e, Application $app, Request $request, RequestContext $context): void
    {
        $this->errors[] = $e->getMessage();
    }
}

/** Executes Octane itself; only its HTTP transport is captured locally. */
final class OctaneLifecycleTest extends TestCase
{
    private const string A = '0198a000-0000-7000-8000-00000000000a';

    private const string B = '0198a000-0000-7000-8000-00000000000b';

    private Worker $worker;

    private LifecycleClient $client;

    private LifecycleTrace $trace;

    private string $temporaryRoot;

    private int $handles = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->temporaryRoot = sys_get_temp_dir().'/corex-octane-test-'.bin2hex(random_bytes(12));
        foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/cache', 'storage/logs'] as $path) {
            (new Filesystem)->makeDirectory($this->temporaryRoot.'/'.$path, 0700, true);
        }

        // Framework cache and all writable storage are isolated before boot.
        putenv('APP_CONFIG_CACHE='.$this->temporaryRoot.'/bootstrap/cache/config.php');
        putenv('APP_SERVICES_CACHE='.$this->temporaryRoot.'/bootstrap/cache/services.php');
        putenv('APP_PACKAGES_CACHE='.$this->temporaryRoot.'/bootstrap/cache/packages.php');
        putenv('LARAVEL_STORAGE_PATH='.$this->temporaryRoot.'/storage');
        $this->trace = new LifecycleTrace;
        $this->client = new LifecycleClient;
        $this->bootWorker();
    }

    private function bootWorker(): void
    {
        $this->worker = new Worker(new ApplicationFactory(__DIR__.'/octane-consumer'), $this->client);

        try {
            $this->worker->boot([LifecycleTrace::class => $this->trace]);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
        self::assertSame('testing', $this->worker->application()->environment());
        self::assertSame(':memory:', $this->worker->application()['config']->get('database.connections.sqlite.database'));
        self::assertStringStartsWith($this->temporaryRoot, $this->worker->application()->storagePath());
    }

    private function handle(string $tenant, bool $fails = false): void
    {
        $this->handles++;
        $this->worker->handle(Request::create('/lifecycle', 'GET', server: ['HTTP_X_TENANT' => $tenant, 'HTTP_X_FAIL' => $fails ? 'yes' : 'no']), new RequestContext);
        self::assertSame($this->worker->application(), Container::getInstance());
        self::assertNull($this->worker->application()['config']->get('lifecycle.leak'));
    }

    private function assertTenantSeparation(): void
    {
        self::assertCount(2, $this->trace->requests);
        [$a, $b] = $this->trace->requests;
        self::assertSame(self::A, $a['tenant']);
        self::assertSame(self::B, $b['tenant']);
        foreach ($a['services'] as $key => $service) {
            self::assertNotSame($service, $b['services'][$key]);
        }

        self::assertStringContainsString(self::A, $a['root']);
        self::assertStringNotContainsString(self::A, $b['root']);
        self::assertStringContainsString(self::B, $b['root']);
        self::assertNull($b['before']);
        self::assertSame(self::A, $a['stored']);
        self::assertSame(self::B, $b['stored']);
    }

    private function assertEvents(int $terminated, int $errors = 0): void
    {
        $counts = array_count_values($this->trace->events);
        self::assertSame(2, $counts[RequestReceived::class] ?? 0);
        self::assertSame(2, $counts[RequestHandled::class] ?? 0);
        self::assertSame($terminated, $counts[RequestTerminated::class] ?? 0);
        self::assertSame($errors, $counts[WorkerErrorOccurred::class] ?? 0);
    }

    public function test_tenant_request_isolation(): void
    {
        $options = $this->worker->application()->make(CoreOptions::class);
        $warmed = $this->warmServices();
        $this->handle(self::A);
        self::assertSame($warmed, $this->trace->requests[0]['services']);
        $this->handle(self::B);
        $this->assertTenantSeparation();
        $this->assertEvents(2);
        self::assertSame([200, 200], $this->client->statuses);
        self::assertSame($options, $this->worker->application()->make(CoreOptions::class));
    }

    public function test_failed_request_cleanup(): void
    {
        $warmed = $this->warmServices();
        $this->handle(self::A, true);
        self::assertSame($warmed, $this->trace->requests[0]['services']);
        $this->handle(self::B);
        $this->assertTenantSeparation();
        $this->assertEvents(2);
        self::assertSame([500, 200], $this->client->statuses);
    }

    /** Pre-warming makes a missing Octane scoped-reset listener observable. */
    private function warmServices(): array
    {
        return array_map(fn (string $key): object => $this->worker->application()->make($key), [TenantContextRestorer::class, TenantStorage::class, Inbox::class, Outbox::class]);
    }

    public function test_worker_error_cleanup(): void
    {
        $this->client->failResponse = true;
        $this->handle(self::A);
        $this->handle(self::B);
        $this->assertTenantSeparation();
        $this->assertEvents(1, 1);
        self::assertSame(['fixture transport failure'], $this->client->errors);
        self::assertSame([200], $this->client->statuses);
    }

    public function test_worker_restart(): void
    {
        $this->handle(self::A);
        $previous = $this->worker->application();
        $this->worker->terminate();
        $this->bootWorker();
        $this->handle(self::B);
        self::assertNotSame($previous, $this->worker->application());
        $this->assertTenantSeparation();
        $this->assertEvents(2);
        self::assertSame(2, array_count_values($this->trace->events)[WorkerStarting::class]);
        self::assertContains(WorkerStopping::class, $this->trace->events);
    }

    protected function tearDown(): void
    {
        try {
            if (isset($this->worker)) {
                $this->worker->terminate();
            }

            $directory = getenv('COREX_OCTANE_TRACE_DIR');

            if (is_string($directory) && $directory !== '') {
                file_put_contents($directory.'/'.$this->name().'.json', json_encode([
                    'case' => $this->name(), 'executed' => true,
                    'implementation' => $this->worker::class,
                    'execution_scope' => 'real-php-worker-handler',
                    'pid' => getmypid(), 'handles' => $this->handles,
                    'events' => array_count_values($this->trace->events),
                    'tenants' => array_column($this->trace->requests, 'tenant'),
                    'statuses' => $this->client->statuses, 'errors' => $this->client->errors,
                ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }
        } finally {
            (new Filesystem)->deleteDirectory($this->temporaryRoot);
            foreach (['APP_CONFIG_CACHE', 'APP_SERVICES_CACHE', 'APP_PACKAGES_CACHE', 'LARAVEL_STORAGE_PATH'] as $name) {
                putenv($name);
            }

            parent::tearDown();
        }
    }
}
