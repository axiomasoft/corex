<?php

declare(strict_types=1);

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\NamedWorkPrincipal;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\Scheduling\Contracts\DueWakeupSweeper;
use CoreX\Scheduling\Data\SweepResult;
use CoreX\Tenancy\Integration\Internal\Wakeup\RootRegistryWakeupHint;
use CoreX\Tenancy\Integration\Internal\Wakeup\RootWakeupReconciler;
use CoreX\Tenancy\Integration\Internal\Wakeup\SweepRootWakeups;
use CoreX\Tenancy\Tests\TestCase;
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

    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');
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

final class WakeupRoutingPrincipal implements DataScopeFactory, NamedWorkPrincipal
{
    private ?string $service = null;

    public function __construct(private readonly string $kind = 'service') {}

    public function run(TenantContext $context, string $serviceId, Closure $work): mixed
    {
        $previous = $this->service;
        $this->service = $serviceId;

        try {
            return $work();
        } finally {
            $this->service = $previous;
        }
    }

    public function forCurrentPrincipal(TenantContext $context): DataScope
    {
        return new DataScope('wakeup-routing', $context->account->id, $context->workspace?->id, $context->account->id, 1,
            $context->account->id, str_repeat('a', 64), $this->kind, $this->service ?? 'ambient-owner');
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
        connections: resolve(ConnectionResolverInterface::class),
        rootConnection: 'pgsql',
        clock: resolve(ClockInterface::class),
        logger: resolve(LoggerInterface::class),
    );
}

function wakeupRoutingReconciler(RootRegistryWakeupHint $hint, WakeupRoutingSweeper $sweeper, ?WakeupRoutingPrincipal $principal = null): RootWakeupReconciler
{
    $principal ??= new WakeupRoutingPrincipal;

    return new RootWakeupReconciler(
        connections: resolve(ConnectionResolverInterface::class),
        rootConnection: 'pgsql',
        clock: resolve(ClockInterface::class),
        hint: $hint,
        tenancy: new WakeupRoutingTenancyManager,
        namedPrincipals: $principal,
        scopes: $principal,
        sweeper: $sweeper,
        leaseSeconds: 60,
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
        ->and(new DateTimeImmutable((string) $row->next_fire_at)->format('c'))->toBe('2026-09-15T12:00:00+00:00')
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

    $reconciler = wakeupRoutingReconciler($hint, $sweeper);

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
    $reconciler = wakeupRoutingReconciler($hint, $sweeper);

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
        wakeupRoutingReconciler(wakeupRoutingHint(), new WakeupRoutingSweeper),
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

it('does not invoke the tenant sweep with an ambient user in place of the named grant', function (): void {
    $account = wakeupRoutingAccount();
    $hint = wakeupRoutingHint();
    $hint->scheduled(new TenantContext($account), new DateTimeImmutable('2000-01-01T00:00:00Z'));
    $called = false;
    $sweeper = new WakeupRoutingSweeper;
    $sweeper->onSweep = static function () use (&$called): void {
        $called = true;
    };

    $reconciler = wakeupRoutingReconciler($hint, $sweeper, new WakeupRoutingPrincipal('user'));
    expect(fn () => $reconciler->sweep(1))->toThrow(ScopeContextMismatch::class)
        ->and($called)->toBeFalse()
        ->and(DB::table('root_wakeup_hints')->where('account_id', $account->id)->exists())->toBeTrue();
});
