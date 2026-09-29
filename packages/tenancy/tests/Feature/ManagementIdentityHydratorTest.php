<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenancyManager;
use CoreX\Tenancy\Membership\Internal\Identity\ManagementIdentityHydrator;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    foreach (['identity-root-fixture', 'identity-tenant-fixture'] as $connection) {
        config(['database.connections.'.$connection => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge($connection);
    }
    $root = DB::connection('identity-root-fixture');
    $root->statement('CREATE TABLE root_accounts (id TEXT PRIMARY KEY, status TEXT, slug TEXT, deleted_at TEXT)');
    $root->statement('CREATE TABLE root_identities (id TEXT PRIMARY KEY, email TEXT, name TEXT, phone TEXT, locale TEXT, updated_at TEXT, deleted_at TEXT)');
    $root->statement('CREATE TABLE root_identity_accounts (identity_id TEXT, account_id TEXT, status TEXT, sync_status TEXT, deleted_at TEXT)');
    DB::connection('identity-tenant-fixture')->statement('CREATE TABLE users (id TEXT PRIMARY KEY, email TEXT UNIQUE, name TEXT, phone TEXT, locale TEXT, is_active BOOLEAN, synced_at TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT, lease TEXT, generation TEXT, epoch TEXT, remember_token TEXT)');
    $this->identity = '11111111-1111-4111-8111-111111111111';
    $this->account = '22222222-2222-4222-8222-222222222222';
    $root->table('root_accounts')->insert(['id' => $this->account, 'status' => 'active', 'slug' => 'fixture']);
    $root->table('root_identities')->insert(['id' => $this->identity, 'email' => 'one@example.test', 'name' => 'Source', 'phone' => '+7000', 'locale' => 'en', 'updated_at' => '2026-09-13 00:00:00']);
    $root->table('root_identity_accounts')->insert(['identity_id' => $this->identity, 'account_id' => $this->account, 'status' => 'active']);
    $this->manager = Mockery::mock(TenancyManager::class);
    $this->manager->shouldReceive('initialized')->andReturn(false)->byDefault();
    $this->manager->shouldReceive('context')->andReturn(null)->byDefault();
    $this->manager->shouldReceive('runFor')->andReturnUsing(function ($account, $workspace, Closure $callback): mixed {
        if ($account->id !== $this->account) {
            throw new RuntimeException('Isolated target unavailable');
        }
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection('identity-tenant-fixture');

        try {
            return $callback();
        } finally {
            DB::setDefaultConnection($original);
        }
    });
    $this->hydrator = new ManagementIdentityHydrator(centralConnection: 'identity-root-fixture', tenancy: $this->manager, managementScope: 'management-root');
});

function identityHydrationRow(object $test, array $overrides = []): void
{
    DB::connection('identity-tenant-fixture')->table('users')->insert([
        'id' => $test->identity, 'email' => 'one@example.test', 'name' => 'Newer', 'phone' => '+7000', 'locale' => 'en', 'is_active' => true,
        'synced_at' => '2026-09-14 00:00:00', 'lease' => 'unchanged', 'generation' => '7', 'epoch' => '9', 'remember_token' => 'old', ...$overrides,
    ]);
}

it('denies independently of unchanged profile clock and preserves fences', function (string $state): void {
    identityHydrationRow($this);
    $members = DB::connection('identity-root-fixture')->table('root_identity_accounts');

    if ($state === 'missing') {
        $members->delete();
    } elseif ($state === 'deleted') {
        $members->update(['deleted_at' => '2026-09-13']);
    } else {
        $members->update(['status' => $state]);
    }
    $this->hydrator->hydrate(identityId: $this->identity, accountId: $this->account);
    $row = DB::connection('identity-tenant-fixture')->table('users')->first();
    expect((bool) $row->is_active)->toBeFalse()->and($row->name)->toBe('Newer')
        ->and($row->lease)->toBe('unchanged')->and($row->generation)->toBe('7')->and($row->epoch)->toBe('9')->and($row->remember_token)->toBe('old');
})->with(['missing', 'invited', 'pending', 'blocked', 'revoked', 'deleted', 'unknown']);

it('cannot grant or renew through new profile hydration or regrant', function (): void {
    $this->hydrator->hydrate(identityId: $this->identity, accountId: $this->account);
    $tenant = DB::connection('identity-tenant-fixture');
    expect((bool) $tenant->table('users')->value('is_active'))->toBeFalse();
    $tenant->table('users')->update(['lease' => 'old', 'generation' => '3', 'epoch' => '4']);
    DB::connection('identity-root-fixture')->table('root_identities')->update(['name' => 'Next', 'updated_at' => '2026-09-15 00:00:00']);
    $this->hydrator->hydrate(identityId: $this->identity, accountId: $this->account);
    $row = $tenant->table('users')->first();
    expect($row->name)->toBe('Next')->and((bool) $row->is_active)->toBeFalse()->and($row->lease)->toBe('old')->and($row->generation)->toBe('3')->and($row->epoch)->toBe('4');
    $this->hydrator->hydrate(identityId: $this->identity, accountId: $this->account);
    expect($tenant->table('users')->count())->toBe(1);
});

it('deactivates missing or deleted identities and accounts without resurrection', function (string $target): void {
    identityHydrationRow($this);
    $root = DB::connection('identity-root-fixture');
    match ($target) {
        'missing-identity' => $root->table('root_identities')->delete(),
        'deleted-identity' => $root->table('root_identities')->update(['deleted_at' => '2026-09-13']),
        'deleted-account' => $root->table('root_accounts')->update(['deleted_at' => '2026-09-13']),
        'blocked-account' => $root->table('root_accounts')->update(['status' => 'blocked']),
        'deleted-user' => DB::connection('identity-tenant-fixture')->table('users')->update(['deleted_at' => '2026-09-13']),
    };
    $this->hydrator->hydrate(identityId: $this->identity, accountId: $this->account);
    $row = DB::connection('identity-tenant-fixture')->table('users')->first();
    expect($row->name)->toBe('Newer');

    expect((bool) $row->is_active)->toBeFalse();

    if ($target === 'deleted-user') {
        expect($row->deleted_at)->not->toBeNull();
    }
})->with(['missing-identity', 'deleted-identity', 'deleted-account', 'blocked-account', 'deleted-user']);

it('keeps shared phone UUIDs separate and refuses email collision without merge', function (): void {
    $this->hydrator->hydrate(identityId: $this->identity, accountId: $this->account);
    $other = '33333333-3333-4333-8333-333333333333';
    $root = DB::connection('identity-root-fixture');
    $root->table('root_identities')->insert(['id' => $other, 'email' => 'two@example.test', 'name' => 'Other', 'phone' => '+7000', 'locale' => 'en', 'updated_at' => '2026-09-13 00:00:00']);
    $root->table('root_identity_accounts')->insert(['identity_id' => $other, 'account_id' => $this->account, 'status' => 'active']);
    $this->hydrator->hydrate(identityId: $other, accountId: $this->account);
    $tenant = DB::connection('identity-tenant-fixture');
    expect($tenant->table('users')->count())->toBe(2);
    $root->table('root_identities')->where('id', $other)->update(['email' => 'one@example.test', 'updated_at' => '2026-09-15 00:00:00']);
    $this->hydrator->hydrate(identityId: $other, accountId: $this->account);
    expect($tenant->table('users')->where('id', $other)->value('email'))->toBe('two@example.test')
        ->and($tenant->table('users')->where('id', $this->identity)->value('email'))->toBe('one@example.test')
        ->and($root->table('root_identity_accounts')->where('identity_id', $other)->value('sync_status'))->toBe('failed');
});

it('denies tenant or absent management authority before root reads or bookkeeping', function (bool $tenant): void {
    if ($tenant) {
        $this->manager->shouldReceive('initialized')->andReturn(true);
    }
    $hydrator = new ManagementIdentityHydrator(centralConnection: 'identity-root-fixture', tenancy: $this->manager, managementScope: $tenant ? 'management-root' : '');
    DB::connection('identity-root-fixture')->enableQueryLog();
    expect(fn () => $hydrator->hydrate(identityId: $this->identity, accountId: $this->account))->toThrow(DomainException::class);
    expect(fn () => $hydrator->fanOut($this->identity))->toThrow(DomainException::class);
    expect(DB::connection('identity-root-fixture')->getQueryLog())->toBe([]);
})->with([true, false]);

it('isolates a failed fan-out target from a healthy UUID target', function (): void {
    $root = DB::connection('identity-root-fixture');
    $otherAccount = '44444444-4444-4444-8444-444444444444';
    $root->table('root_accounts')->insert(['id' => $otherAccount, 'status' => 'active']);
    $root->table('root_identity_accounts')->insert(['identity_id' => $this->identity, 'account_id' => $otherAccount, 'status' => 'active']);
    $this->hydrator->fanOut($this->identity);
    expect($root->table('root_identity_accounts')->where('account_id', $otherAccount)->value('sync_status'))->toBe('failed')
        ->and($root->table('root_identity_accounts')->where('account_id', $this->account)->value('sync_status'))->toBe('synced');
});

it('rejects malformed UUID before querying the management store', function (): void {
    DB::connection('identity-root-fixture')->enableQueryLog();
    expect(fn () => $this->hydrator->hydrate(identityId: '123', accountId: $this->account))->toThrow(DomainException::class);
    expect(DB::connection('identity-root-fixture')->getQueryLog())->toBe([]);
});

it('preserves committed deny even when newer profile email conflicts', function (): void {
    identityHydrationRow($this, ['synced_at' => '2026-09-12 00:00:00']);
    DB::connection('identity-tenant-fixture')->table('users')->insert(['id' => '55555555-5555-4555-8555-555555555555', 'email' => 'collision@example.test', 'is_active' => true]);
    $root = DB::connection('identity-root-fixture');
    $root->table('root_identities')->update(['email' => 'collision@example.test']);
    $root->table('root_identity_accounts')->update(['status' => 'blocked']);
    $this->hydrator->hydrate(identityId: $this->identity, accountId: $this->account);
    $row = DB::connection('identity-tenant-fixture')->table('users')->where('id', $this->identity)->first();
    expect((bool) $row->is_active)->toBeFalse()->and($row->email)->toBe('one@example.test')
        ->and($root->table('root_identity_accounts')->value('sync_status'))->toBe('failed');
});
