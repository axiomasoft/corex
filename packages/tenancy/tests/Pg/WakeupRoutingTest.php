<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Integration\Internal\Wakeup\RootRegistryWakeupHint;
use CoreX\Tenancy\Integration\Internal\Wakeup\RootWakeupReconciler;
use CoreX\Tenancy\Integration\Internal\Wakeup\SweepRootWakeups;
use CoreX\Tenancy\Tests\TestCase;
use CoreX\Wakeup\Contracts\DueWakeupSweeper;
use CoreX\Wakeup\Data\SweepResult;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

uses(TestCase::class)->group('pg');

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(__DIR__.'/../../database/migrations/root'),
        '--realpath' => true,
        '--force' => true,
    ])->run();
});

final class WakeupRoutingTenancyManager implements TenancyManager
{
    /** @var list<string> */
    public array $visited = [];

    public function context(): ?TenantContext
    {
        return null;
    }

    public function initialized(): bool
    {
        return false;
    }

    public function initialize(AccountRef $account): void {}

    public function setWorkspace(?WorkspaceRef $workspace): void {}

    public function end(): void {}

    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        $this->visited[] = $account->id;

        return $callback();
    }
}

final class WakeupRoutingSweeper implements DueWakeupSweeper
{
    /** @var array<string, DateTimeImmutable|null> */
    public array $due = [];

    /** @var null|Closure(TenantContext):void */
    public ?Closure $onSweep = null;

    public function sweep(TenantContext $context, int $limit): SweepResult
    {
        if ($this->onSweep !== null) {
            ($this->onSweep)($context);
        }

        return new SweepResult(claimed: 0, completed: 0, retried: 0, failed: 0, skipped: 0, remainingDue: false, nextFireAt: $this->nextDue($context));
    }

    public function nextDue(TenantContext $context): ?DateTimeImmutable
    {
        return $this->due[$context->account->id] ?? null;
    }
}

function wakeupRoutingAccount(): AccountRef
{
    $clusterId = (string) Str::uuid();
    $identityId = (string) Str::uuid();
    $accountId = (string) Str::uuid();

    DB::table('root_clusters')->insert([
        'id' => $clusterId,
        'name' => 'wakeup-routing-'.$clusterId,
        'pg_host_writer' => 'writer.test',
        'pgbouncer_host' => 'pool.test',
        'app_db_user' => 'corex',
    ]);
    DB::table('root_identities')->insert(['id' => $identityId, 'email' => $identityId.'@example.test']);
    DB::table('root_accounts')->insert([
        'id' => $accountId,
        'slug' => 'wakeup-'.$accountId,
        'cluster_id' => $clusterId,
        'db_name' => 'acc_wakeup_'.str_replace('-', '', $accountId).'_test',
        'status' => 'active',
        'owner_identity_id' => $identityId,
        'template_version' => 1,
        'embedding_model' => 'test',
        'embedding_dim' => 1,
    ]);

    return new AccountRef(id: $accountId, slug: 'wakeup-'.$accountId, status: 'active', features: [], limits: []);
}

function wakeupRoutingHint(): RootRegistryWakeupHint
{
    return new RootRegistryWakeupHint(
        connections: app(ConnectionResolverInterface::class),
        rootConnection: 'pgsql',
        clock: app(ClockInterface::class),
        logger: app(LoggerInterface::class),
    );
}

it('keeps the earliest root hint and increments its revision on every upsert', function (): void {
    $hint = wakeupRoutingHint();
    $context = new TenantContext(new AccountRef(
        id: '11111111-1111-7111-8111-111111111111',
        slug: 'hint-test',
        status: 'active',
        features: [],
        limits: [],
    ));

    $hint->scheduled(context: $context, fireAt: new DateTimeImmutable('2026-09-15T12:05:00Z'));
    $hint->scheduled(context: $context, fireAt: new DateTimeImmutable('2026-09-15T12:10:00Z'));
    $hint->scheduled(context: $context, fireAt: new DateTimeImmutable('2026-09-15T12:00:00Z'));

    $row = DB::connection('pgsql')->table('root_wakeup_hints')->first();

    expect($row->hint_revision)->toBe(3)
        ->and((new DateTimeImmutable((string) $row->next_fire_at))->format('c'))->toBe('2026-09-15T12:00:00+00:00')
        ->and($row->claim_epoch)->toBe(0);
});

it('does not let a stale root claimant delete a concurrently refreshed hint', function (): void {
    $account = wakeupRoutingAccount();
    $context = new TenantContext(account: $account);
    $hint = wakeupRoutingHint();
    $hint->scheduled(context: $context, fireAt: new DateTimeImmutable('2000-01-01T00:00:00Z'));
    $sweeper = new WakeupRoutingSweeper;
    $sweeper->onSweep = static function (TenantContext $current) use ($hint): void {
        $hint->scheduled(context: $current, fireAt: new DateTimeImmutable('2000-01-01T00:01:00Z'));
    };

    $reconciler = new RootWakeupReconciler(
        connections: app(ConnectionResolverInterface::class),
        rootConnection: 'pgsql',
        clock: app(ClockInterface::class),
        hint: wakeupRoutingHint(),
        tenancy: new WakeupRoutingTenancyManager,
        sweeper: $sweeper,
        leaseSeconds: 60,
    );

    expect($reconciler->sweep(limit: 1))->toBe(1);

    $row = DB::table('root_wakeup_hints')->where('account_id', $account->id)->first();
    expect($row)->not->toBeNull()
        ->and($row->hint_revision)->toBe(2)
        ->and($row->claim_epoch)->toBe(1);
});

it('reclaims expired root leases and rebuilds lost hints in bounded account pages', function (): void {
    $account = wakeupRoutingAccount();
    $context = new TenantContext(account: $account);
    $hint = wakeupRoutingHint();
    $hint->scheduled(context: $context, fireAt: new DateTimeImmutable('2000-01-01T00:00:00Z'));
    DB::table('root_wakeup_hints')->where('account_id', $account->id)->update([
        'leased_until' => now()->subMinute(),
        'claim_epoch' => 7,
    ]);
    $sweeper = new WakeupRoutingSweeper;
    $reconciler = new RootWakeupReconciler(
        connections: app(ConnectionResolverInterface::class),
        rootConnection: 'pgsql',
        clock: app(ClockInterface::class),
        hint: wakeupRoutingHint(),
        tenancy: new WakeupRoutingTenancyManager,
        sweeper: $sweeper,
        leaseSeconds: 60,
    );

    expect($reconciler->sweep(limit: 1))->toBe(1)
        ->and(DB::table('root_wakeup_hints')->where('account_id', $account->id)->exists())->toBeFalse();

    $accounts = [];
    for ($index = 0; $index < 101; $index++) {
        $accounts[] = wakeupRoutingAccount();
    }
    foreach ($accounts as $indexedAccount) {
        $sweeper->due[$indexedAccount->id] = new DateTimeImmutable('2030-01-01T00:00:00Z');
    }

    $first = $reconciler->reconcile(limit: 100);
    $second = $reconciler->reconcile(limit: 100, afterAccountId: $first['next_cursor']);

    expect($first['processed'])->toBe(100)
        ->and($first['next_cursor'])->not->toBeNull()
        ->and($second['processed'])->toBe(2)
        ->and($second['next_cursor'])->toBeNull()
        ->and(DB::table('root_wakeup_hints')->count())->toBe(101);
});

it('runs valid root sweeps and rejects invalid limits before doing root work', function (): void {
    $this->app->instance(
        RootWakeupReconciler::class,
        new RootWakeupReconciler(
            connections: app(ConnectionResolverInterface::class),
            rootConnection: 'pgsql',
            clock: app(ClockInterface::class),
            hint: wakeupRoutingHint(),
            tenancy: new WakeupRoutingTenancyManager,
            sweeper: new WakeupRoutingSweeper,
            leaseSeconds: 60,
        ),
    );
    $invalid = new SweepRootWakeups;
    $invalid->setLaravel($this->app);
    $invalidOutput = new BufferedOutput;
    $sweep = new SweepRootWakeups;
    $sweep->setLaravel($this->app);
    $sweepOutput = new BufferedOutput;
    $reconcile = new SweepRootWakeups;
    $reconcile->setLaravel($this->app);
    $reconcileOutput = new BufferedOutput;

    expect($invalid->run(new ArrayInput(['--limit' => '0']), $invalidOutput))->toBe(2)
        ->and($invalidOutput->fetch())->toContain('between 1 and 1000')
        ->and($sweep->run(new ArrayInput(['--limit' => '1']), $sweepOutput))->toBe(0)
        ->and($sweepOutput->fetch())->toContain('Swept 0 account hints.')
        ->and($reconcile->run(new ArrayInput(['--limit' => '1', '--reconcile' => true]), $reconcileOutput))->toBe(0)
        ->and($reconcileOutput->fetch())->toContain('Reconciled 0 account wakeup hints.');
});
