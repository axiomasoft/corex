<?php

declare(strict_types=1);

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Support\Facades\DB;

it('restores the exact outer workspace after a failing nested work scope', function (): void {
    $host = WorkScopeFixture::install(app());
    $contexts = LocalWorkContexts::install(app());
    $account = new AccountRef('account-1', 'test', 'active', [], []);
    $workspace = new WorkspaceRef('workspace', 'workspace', 'team', '/workspace', null);
    $contexts->initialize($account);
    $contexts->setWorkspace($workspace);
    $outer = $host->scope();
    $contexts->setWorkspace(null);
    $inner = $host->scope();
    $proof = resolve(StoredWorkIssuer::class)->issue($inner, 'test.scope', 'nested');
    $contexts->setWorkspace($workspace);
    $runner = resolve(StoredWorkRunner::class);
    expect(fn () => $runner->runStored($inner, $proof, function ($context) use ($inner): void {
        expect($context->workspace)->toBeNull();
        expect(resolve(CurrentWorkScope::class)->assert($inner, DB::connection()))->toBe($context);

        throw new RuntimeException('work failed');
    }))->toThrow(RuntimeException::class, 'work failed');
    expect(resolve(CurrentWorkScope::class)->assert($outer)->workspace)->toBe($workspace);
});

it('rejects principal substitution and an independently created connection', function (): void {
    $host = WorkScopeFixture::install(app());
    $scope = $host->scope();
    expect(fn () => resolve(CurrentWorkScope::class)->assert($scope, DB::connection('work-scope-control')))->toThrow(ScopeContextMismatch::class);
    $host->principal = new ScopePrincipal('user', 'revoked');
    expect(fn () => resolve(WorkScopeRunner::class)->run($scope, fn () => throw new LogicException))->toThrow(ScopeContextMismatch::class);
});

it('rejects forged or substituted durable provenance before admission', function (): void {
    $host = WorkScopeFixture::install(app());
    $scope = $host->scope();
    $proof = resolve(StoredWorkIssuer::class)->issue($scope, 'test.job', 'immutable-request');
    $forged = new StoredWorkProof($proof->owner, 'other-request', $proof->seal);
    $runner = resolve(StoredWorkRunner::class);
    expect(fn () => $runner->runStored($scope, $forged, static fn () => throw new LogicException))->toThrow(ScopeContextMismatch::class);
    $host->principal = new ScopePrincipal('user', 'other');
    expect(fn () => $runner->runStored($host->scope(), $proof, static fn () => throw new LogicException))->toThrow(ScopeContextMismatch::class)
        ->and(DB::connection('work-scope-control')->table('corex_scope_tickets')->count())->toBe(0);
});

it('refuses scope issuance for a sibling workspace supplied by application code', function (): void {
    $fixture = WorkScopeFixture::install(app());
    $contexts = LocalWorkContexts::install(app());
    $account = new AccountRef('account', 'acct', 'active', [], []);
    $home = new WorkspaceRef('home', 'home', 'team', '/home', null);
    $sibling = new WorkspaceRef('sibling', 'sibling', 'team', '/sibling', null);
    $contexts->initialize($account);
    $contexts->setWorkspace($home);
    $issued = $fixture->scope();
    $factory = resolve(DataScopeFactory::class);
    expect(fn () => $factory->forCurrentPrincipal(new TenantContext($account, $sibling)))->toThrow(ScopeContextMismatch::class)
        ->and($factory->forCurrentPrincipal($contexts->current()))->toEqual($issued)
        ->and(DB::connection('work-scope-control')->table('corex_scope_tickets')->count())->toBe(0);
});

it('separates user and service grants that share the same principal identifier', function (): void {
    $fixture = WorkScopeFixture::install(app(), 'operator', 'service');
    $service = $fixture->scope();
    $fixture->principal = new ScopePrincipal('user', 'operator');
    $user = $fixture->scope();
    $work = resolve(WorkScopeRunner::class);
    expect(fn () => $work->run($service, static fn () => throw new LogicException))->toThrow(ScopeContextMismatch::class)
        ->and(fn () => resolve(StoredWorkIssuer::class)->issue($service, 'test.authority', 'future-service-work'))->toThrow(ScopeContextMismatch::class)
        ->and($work->run($user, static fn () => 'user-work'))->toBe('user-work');
});

it('keeps the outer transaction and workspace intact when durable work requests another workspace', function (): void {
    $host = WorkScopeFixture::install(app());
    $contexts = LocalWorkContexts::install(app());
    $account = new AccountRef('account', 'acct', 'active', [], []);
    $contexts->initialize($account);
    $wide = $host->scope();
    $proof = resolve(StoredWorkIssuer::class)->issue($wide, 'test.authority', 'account-work');
    $workspace = new WorkspaceRef('team', 'team', 'team', '/team', null);
    $contexts->setWorkspace($workspace);
    DB::connection()->transaction(function () use ($wide, $proof, $workspace, $contexts): void {
        expect(fn () => resolve(StoredWorkRunner::class)->runStored($wide, $proof, static fn () => throw new LogicException))->toThrow(ScopeContextMismatch::class)
            ->and(DB::transactionLevel())->toBe(1)
            ->and($contexts->current()->workspace)->toBe($workspace)
            ->and(DB::connection('work-scope-control')->table('corex_scope_tickets')->count())->toBe(0);
    });
});

it('stops a resident queue worker after its callback leaves the wrong default connection', function (): void {
    $scope = WorkScopeFixture::install(app())->scope();
    $runner = resolve(WorkScopeRunner::class);
    $worker = resolve('queue.worker');
    $original = DB::getDefaultConnection();

    try {
        expect(fn () => $runner->run($scope, static fn () => DB::setDefaultConnection('work-scope-control')))->toThrow(ScopeContextMismatch::class);
    } finally {
        DB::setDefaultConnection($original);
    }
    expect($worker->shouldQuit)->toBeTrue()
        ->and(fn () => $runner->run($scope, static fn () => throw new LogicException))->toThrow(RuntimeException::class, 'The worker could not restore its previous work scope.')
        ->and(DB::connection('work-scope-control')->table('corex_scope_tickets')->count())->toBe(1);
});
