<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Laravel\WorkQueueHandlers;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

final class FrameworkIsolationCentralJob implements ShouldQueue
{
    public static array $observed = [];

    public function __construct(private readonly bool $fails) {}

    public function handle(): void
    {
        self::$observed[] = [Auth::id(), Auth::guard('other')->id(), request()->user()?->getAuthIdentifier()];

        if ($this->fails) {
            throw new RuntimeException('Deliberate central job failure.');
        }
    }
}

final class FrameworkIsolationContextModel extends Model
{
    protected $table = 'queue_context_models';

    protected $connection = 'context_tenant';
}

final class FrameworkIsolationContextJob implements ShouldQueue
{
    public static array $observed = [];

    public function handle(): void
    {
        self::$observed[] = [Context::get('trace'), Context::getHidden('secret-trace'), Context::has('record'), Context::hasHidden('record')];
    }
}

final class FrameworkIsolationAfterCommitJob implements ShouldQueueAfterCommit
{
    public static array $workspaces = [];

    public function handle(TenantContextResolver $contexts): void
    {
        self::$workspaces[] = $contexts->current()->workspace?->id;
    }
}

it('isolates a central sync job from logged-in session guards and restores the outer request', function (bool $fails): void {
    config([
        'auth.defaults.guard' => 'web',
        'auth.guards.web' => ['driver' => 'session', 'provider' => 'test-users'],
        'auth.guards.other' => ['driver' => 'session', 'provider' => 'test-users'],
        'auth.providers.test-users' => ['driver' => 'database', 'table' => 'test_users'],
    ]);
    DB::getSchemaBuilder()->create('test_users', static function (Blueprint $table): void {
        $table->id();
        $table->string('password');
    });
    DB::table('test_users')->insert([['id' => 1, 'password' => 'unused'], ['id' => 2, 'password' => 'unused']]);
    Auth::guard('web')->loginUsingId(1);
    Auth::guard('other')->loginUsingId(2);
    app()->instance('request', Request::create('/account'));
    expect(request()->user()?->getAuthIdentifier())->toBe(1);
    app(WorkQueueHandlers::class)->central(FrameworkIsolationCentralJob::class);
    FrameworkIsolationCentralJob::$observed = [];

    $dispatch = static fn () => Queue::connection('sync')->push(new FrameworkIsolationCentralJob($fails));

    if ($fails) {
        expect($dispatch)->toThrow(RuntimeException::class, 'Deliberate central job failure.');
    } else {
        $dispatch();
    }

    expect(FrameworkIsolationCentralJob::$observed)->toBe([[null, null, null]])
        ->and(Auth::id())->toBe(1)
        ->and(Auth::guard('other')->id())->toBe(2)
        ->and(request()->user()?->getAuthIdentifier())->toBe(1);
})->with([false, true]);

it('propagates scalar log context without hydrating an Eloquent model on the worker connection', function (): void {
    config([
        'database.connections.context_tenant' => ['driver' => 'sqlite', 'database' => ':memory:'],
        'database.connections.context_other' => ['driver' => 'sqlite', 'database' => ':memory:'],
        'queue.connections.database.connection' => 'testbench',
    ]);
    foreach (['context_tenant' => 'producer', 'context_other' => 'worker'] as $name => $owner) {
        $connection = DB::connection($name);
        $connection->getSchemaBuilder()->create('queue_context_models', static function (Blueprint $table): void {
            $table->id();
            $table->string('owner');
        });
        $connection->table('queue_context_models')->insert(['id' => 1, 'owner' => $owner]);
    }
    DB::getSchemaBuilder()->create('jobs', static function (Blueprint $table): void {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
    FrameworkIsolationContextJob::$observed = [];
    app(WorkQueueHandlers::class)->central(FrameworkIsolationContextJob::class);
    $record = FrameworkIsolationContextModel::findOrFail(1);
    expect($record->owner)->toBe('producer');
    Context::add(['trace' => 'producer-trace', 'record' => $record]);
    Context::addHidden(['secret-trace' => 'producer-hidden', 'record' => $record]);
    $queue = Queue::connection('database');
    $queue->push(new FrameworkIsolationContextJob);

    $tenant = DB::connection('context_tenant');
    $tenant->setPdo(DB::connection('context_other')->getPdo());
    expect(FrameworkIsolationContextModel::findOrFail(1)->owner)->toBe('worker');
    $tenant->enableQueryLog();
    Context::flush()->add('trace', 'outer-trace')->addHidden('secret-trace', 'outer-hidden');
    app('queue.worker')->process('database', $queue->pop(), new WorkerOptions(maxTries: 1));

    expect($tenant->getQueryLog())->toBe([])
        ->and(FrameworkIsolationContextJob::$observed)->toBe([['producer-trace', 'producer-hidden', false, false]])
        ->and(Context::get('trace'))->toBe('outer-trace')
        ->and(Context::getHidden('secret-trace'))->toBe('outer-hidden')
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('freezes the workspace of a deferred sync job before the outer transaction commits', function (): void {
    $contexts = LocalWorkContexts::install(app());
    $authority = WorkScopeFixture::install(app());
    $account = new AccountRef('account', 'account', 'active', [], []);
    $outer = new WorkspaceRef('w1', 'outer', 'company', 'outer', null);
    $producer = new WorkspaceRef('w2', 'producer', 'company', 'producer', null);
    $contexts->initialize($account);
    $contexts->setWorkspace($outer);
    $authority->scope();
    app(WorkQueueHandlers::class)->tenant(FrameworkIsolationAfterCommitJob::class);
    FrameworkIsolationAfterCommitJob::$workspaces = [];

    DB::connection()->transaction(function () use ($contexts, $account, $outer, $producer): void {
        $contexts->runFor($account, $producer, static function (): void {
            Queue::connection('sync')->push(new FrameworkIsolationAfterCommitJob);
        });
        expect(FrameworkIsolationAfterCommitJob::$workspaces)->toBe([])
            ->and($contexts->current()->workspace?->id)->toBe($outer->id);
    });

    expect(FrameworkIsolationAfterCommitJob::$workspaces)->toBe([$producer->id])
        ->and($contexts->current()->workspace?->id)->toBe($outer->id);
});
