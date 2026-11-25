<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Laravel\WorkQueueHandlers;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class LifecycleQueueCommand implements ShouldQueue
{
    /** @var list<array{string, CurrentWorkScope, TenantStorage, string}> */
    public static array $observed = [];

    public function __construct(public readonly bool $fails) {}

    public function handle(TenantContextResolver $contexts, CurrentWorkScope $work, TenantStorage $storage): void
    {
        self::$observed[] = [$contexts->current()->account->id, $work, $storage, $storage->disk()->path('')];

        if ($this->fails) {
            throw new RuntimeException('lifecycle job failed');
        }
    }
}

it('restores real database queue work in one Laravel daemon after both success and failure', function (bool $failure): void {
    $root = sys_get_temp_dir().'/corex-worker-'.bin2hex(random_bytes(12));
    config(['corex.storage.durable_root' => $root, 'corex.storage.ephemeral_root' => $root,
        'queue.connections.database.connection' => 'testbench', 'queue.failed.driver' => null, 'logging.default' => 'null']);
    app()->forgetInstance(CoreOptions::class);
    $fixture = WorkScopeFixture::install(app());
    $contexts = LocalWorkContexts::install(app());
    resolve(WorkQueueHandlers::class)->tenant(LifecycleQueueCommand::class);
    DB::connection()->getSchemaBuilder()->create('jobs', static function (Blueprint $table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    $attempts = [];
    resolve('events')->listen(JobAttempted::class, function (JobAttempted $event) use (&$attempts): void {
        $attempts[] = $event->exception !== null;
    });
    LifecycleQueueCommand::$observed = [];
    $ids = ['0198a000-0000-7000-8000-00000000000a', '0198a000-0000-7000-8000-00000000000b'];
    foreach ($ids as $i => $id) {
        $contexts->initialize(new AccountRef($id, 'test', 'active', [], []));
        $fixture->scope();
        Queue::connection('database')->push(new LifecycleQueueCommand($i === 0 && $failure));
    }
    $contexts->end();
    $options = resolve(CoreOptions::class);
    $async = function_exists('pcntl_async_signals') ? pcntl_async_signals() : null;
    $signals = [];

    if ($async !== null) {
        foreach ([SIGQUIT, SIGTERM, SIGINT, SIGUSR2, SIGCONT, SIGALRM] as $signal) {
            $signals[$signal] = pcntl_signal_get_handler($signal);
        }
    }

    try {
        $exit = resolve('queue.worker')->daemon('database', 'default', new WorkerOptions(memory: 512, timeout: 0, sleep: 0, maxTries: 1, stopWhenEmpty: true, maxJobs: 2));
        $rows = LifecycleQueueCommand::$observed;
        expect($exit)->toBe(0)->and($attempts)->toBe([$failure, false])->and(array_column($rows, 0))->toBe($ids)
            ->and($rows[0][1])->not->toBe($rows[1][1])->and($rows[0][2])->not->toBe($rows[1][2])
            ->and($rows[0][3])->toContain($ids[0])->and($rows[1][3])->toContain($ids[1])
            ->and($contexts->context())->toBeNull()->and(resolve(CoreOptions::class))->toBe($options)
            ->and(DB::table('jobs')->count())->toBe(0);
    } finally {
        foreach ($signals as $signal => $previous) {
            pcntl_signal($signal, $previous);
        }

        if ($async !== null) {
            pcntl_async_signals($async);
        }
        (new Filesystem)->deleteDirectory($root);
    }
})->with([false, true]);
