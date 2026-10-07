<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Storage\Contracts\TenantStorage;
use Illuminate\Container\Container;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function (): void {
    $this->lifecycleRoot = sys_get_temp_dir().'/corex-queue-test-'.bin2hex(random_bytes(12));
    resolve('config')->set('corex.storage.durable_root', $this->lifecycleRoot.'/durable');
    resolve('config')->set('corex.storage.ephemeral_root', $this->lifecycleRoot.'/ephemeral');
    app()->forgetInstance(CoreOptions::class);
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory($this->lifecycleRoot);
});
final readonly class LifecycleContextResolver implements TenantContextResolver
{
    public function __construct(private string $tenant) {}

    public function current(): TenantContext
    {
        return new TenantContext(account: new AccountRef(id: $this->tenant, slug: $this->tenant, status: 'active', features: [], limits: []));
    }
}

/** The transport fixture supplies jobs; Laravel's actual daemon owns execution and reset. */
final class LifecycleQueueJob extends Job implements Illuminate\Contracts\Queue\Job
{
    public ?TenantContextRestorer $restorer = null;

    public ?TenantStorage $storage = null;

    public ?string $root = null;

    public function __construct(Application $app, private readonly string $tenant, private readonly bool $throws)
    {
        $this->container = $app;
        $this->connectionName = 'sync';
        $this->queue = 'default';
    }

    public function getJobId(): string
    {
        return $this->tenant;
    }

    public function getRawBody(): string
    {
        return json_encode(['uuid' => $this->tenant, 'job' => stdClass::class, 'displayName' => self::class, 'data' => []], JSON_THROW_ON_ERROR);
    }

    public function attempts(): int
    {
        return 1;
    }

    public function fire(): void
    {
        $this->container->instance(TenantContextResolver::class, new LifecycleContextResolver($this->tenant));
        $this->restorer = $this->container->make(TenantContextRestorer::class);
        $this->storage = $this->container->make(TenantStorage::class);
        $this->root = $this->storage->disk()->path('');

        if ($this->throws) {
            throw new RuntimeException('lifecycle job failed');
        }

        $this->delete();
    }
}

it('resets CoreX scoped services in a real queue daemon after success and failure', function (bool $failure): void {
    $options = resolve(CoreOptions::class);
    $a = new LifecycleQueueJob(app(), '0198a000-0000-7000-8000-00000000000a', $failure);
    $b = new LifecycleQueueJob(app(), '0198a000-0000-7000-8000-00000000000b', false);
    $jobs = [$a, $b];
    $attempts = [];
    resolve(ExceptionHandler::class)->reportable(static function (Throwable $exception): void {
        if ($exception->getMessage() !== 'lifecycle job failed') {
            throw $exception;
        }
    });
    resolve('config')->set('logging.default', 'null');
    resolve('events')->listen(JobAttempted::class, function (JobAttempted $event) use (&$attempts): void {
        $attempts[] = $event->exception !== null;
    });
    Worker::popUsing('corex-lifecycle', static function () use (&$jobs): ?Job {
        return array_shift($jobs);
    });
    $worker = resolve('queue.worker');
    $worker->setName('corex-lifecycle');
    $signalHandlers = [];
    $asyncSignals = function_exists('pcntl_async_signals') ? pcntl_async_signals() : null;

    if (function_exists('pcntl_signal_get_handler')) {
        foreach ([SIGQUIT, SIGTERM, SIGINT, SIGUSR2, SIGCONT, SIGALRM] as $signal) {
            $signalHandlers[$signal] = pcntl_signal_get_handler($signal);
        }
    }

    try {
        $exit = $worker->daemon('sync', 'default', new WorkerOptions(memory: 512, timeout: 0, sleep: 0, maxTries: 1, stopWhenEmpty: true, maxJobs: 2));
    } finally {
        Worker::popUsing('corex-lifecycle', null);
        app()->forgetScopedInstances();

        foreach ($signalHandlers as $signal => $handler) {
            pcntl_signal($signal, $handler);
        }

        if ($asyncSignals !== null) {
            pcntl_async_signals($asyncSignals);
        }
    }

    expect($exit)->toBe(0)
        ->and($attempts)->toBe([$failure, false])
        ->and($a->hasFailed())->toBe($failure)
        ->and($b->isDeleted())->toBeTrue()
        ->and($a->restorer)->not->toBeNull()
        ->and($b->restorer)->not->toBe($a->restorer)
        ->and($b->storage)->not->toBe($a->storage)
        ->and($a->root)->toContain($a->getJobId())->not->toContain($b->getJobId())
        ->and($b->root)->toContain($b->getJobId())->not->toContain($a->getJobId())
        ->and(resolve(CoreOptions::class))->toBe($options)
        ->and(resolve(TenantContextRestorer::class))->not->toBe($b->restorer);
})->with(['success' => false, 'failure' => true]);

it('detects fixture lifecycle omissions and a singleton registered in place of a scoped binding', function (bool $singleton, bool $reset): void {
    $container = app();

    if ($singleton) {
        $container = new Container;
        $container->singleton(TenantContextRestorer::class, static fn (): object => resolve(TenantContextRestorer::class));
    }

    app()->instance(TenantContextResolver::class, new LifecycleContextResolver('0198a000-0000-7000-8000-00000000000a'));
    $before = $container->make(TenantContextRestorer::class);

    if ($reset) {
        $container->forgetScopedInstances();
    }

    app()->instance(TenantContextResolver::class, new LifecycleContextResolver('0198a000-0000-7000-8000-00000000000b'));

    expect(fn () => Assert::assertNotSame($before, $container->make(TenantContextRestorer::class)))
        ->toThrow(AssertionFailedError::class);
})->with(['missing reset' => [false, false], 'singleton' => [true, true]]);
