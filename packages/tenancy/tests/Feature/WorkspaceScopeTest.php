<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Laravel\Scopes\WorkspaceScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses()->group('pg');

/** Test double — a plain workspace_scoped entity; CRM and tasks own the production tables. */
final class WorkspaceScopedRecordStub extends Model
{
    protected $table = 'wsp_scope_stub_records';

    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['id', 'workspace_id', 'name'];

    protected static function booted(): void
    {
        self::addGlobalScope(new WorkspaceScope);
    }
}

/** Minimal in-memory {@see TenancyManager} — the runtime adapter requires a physical database context this unit test does not need. */
final class FakeTenancyManagerStub implements TenancyManager
{
    private ?TenantContext $ctx = null;

    public function setContext(?WorkspaceRef $workspace): void
    {
        $this->ctx = new TenantContext(new AccountRef(id: 'acc-1', slug: 'acc', status: 'active', features: [], limits: []), $workspace);
    }

    public function context(): ?TenantContext
    {
        return $this->ctx;
    }

    public function initialized(): bool
    {
        return $this->ctx !== null;
    }

    public function initialize(AccountRef $account): void
    {
        $this->ctx = new TenantContext($account);
    }

    public function setWorkspace(?WorkspaceRef $workspace): void
    {
        if ($this->ctx !== null) {
            $this->ctx = $this->ctx->forWorkspace($workspace);
        }
    }

    public function end(): void
    {
        $this->ctx = null;
    }

    public function runFor(AccountRef $account, ?WorkspaceRef $workspace, Closure $callback): mixed
    {
        $this->ctx = new TenantContext($account, $workspace);

        try {
            return $callback();
        } finally {
            $this->ctx = null;
        }
    }
}

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    Schema::create('wsp_scope_stub_records', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('workspace_id')->nullable();
        $table->string('name');
    });

    app()->instance(TenancyManager::class, new FakeTenancyManagerStub);
});

function seedScopeStubRecords(): array
{
    $workspaceScoped = (string) Str::uuid7();
    $otherWorkspaceScoped = (string) Str::uuid7();
    $accountWide = (string) Str::uuid7();

    DB::table('wsp_scope_stub_records')->insert([
        ['id' => $workspaceScoped, 'workspace_id' => 'ws-a', 'name' => 'in ws-a'],
        ['id' => $otherWorkspaceScoped, 'workspace_id' => 'ws-b', 'name' => 'in ws-b'],
        ['id' => $accountWide, 'workspace_id' => null, 'name' => 'account-wide'],
    ]);

    return [$workspaceScoped, $otherWorkspaceScoped, $accountWide];
}

it('with a narrowed workspace, the scope keeps that workspace\'s rows plus account-wide (NULL) rows', function (): void {
    [$workspaceScoped, $otherWorkspaceScoped, $accountWide] = seedScopeStubRecords();

    /** @var FakeTenancyManagerStub $manager */
    $manager = resolve(TenancyManager::class);
    $manager->setContext(new WorkspaceRef(id: 'ws-a', slug: 'a', type: 'company', path: 'a', parentId: null));

    $ids = WorkspaceScopedRecordStub::query()->pluck('id')->all();

    expect($ids)->toContain($workspaceScoped)
        ->and($ids)->toContain($accountWide)
        ->and($ids)->not->toContain($otherWorkspaceScoped);
});

it('with no workspace narrowed (account-wide/admin context), the scope does not filter at all', function (): void {
    seedScopeStubRecords();

    /** @var FakeTenancyManagerStub $manager */
    $manager = resolve(TenancyManager::class);
    $manager->initialize(new AccountRef(id: 'acc-1', slug: 'acc', status: 'active', features: [], limits: []));

    $ids = WorkspaceScopedRecordStub::query()->pluck('id')->all();

    expect($ids)->toHaveCount(3);
});
