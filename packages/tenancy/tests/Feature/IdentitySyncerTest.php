<?php

declare(strict_types=1);

use CoreX\Context\Contracts\IdentityPasswordRehasher;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Identity\Contracts\IdentitySyncer;
use CoreX\Tenancy\Identity\Events\UserSynced;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

uses()->group('pg');

const ROOT_MIGRATIONS_PATH_IS = __DIR__.'/../../database/migrations/root';
const TENANT_MIGRATIONS_PATH_IS = __DIR__.'/../../database/migrations/tenant';
// P2.6's integration-FK migration (C4/D14) needs sys_departments — owned by
// axiomasoft/corex, assembled here the same way a real golden-template build would.
const CORE_MIGRATIONS_PATH_IS = __DIR__.'/../../vendor/axiomasoft/corex/database/migrations/pg';

const TENANT_DB_IS = 'acc_is_test';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_IS),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    DB::statement('DROP DATABASE IF EXISTS '.TENANT_DB_IS);
    DB::statement('CREATE DATABASE '.TENANT_DB_IS);

    config(['queue.default' => 'sync']);
});

afterEach(function (): void {
    DB::purge('pgsql');
    DB::purge('tenant');
    DB::statement('DROP DATABASE IF EXISTS '.TENANT_DB_IS);
});

function makeClusterIS(): string
{
    $id = (string) Str::uuid();

    DB::table('root_clusters')->insert([
        'id' => $id,
        'name' => 'cell-'.$id,
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer-a.local',
        'app_db_user' => 'corex',
    ]);

    return $id;
}

function makeIdentityIS(array $overrides = []): string
{
    $id = (string) Str::uuid();

    DB::table('root_identities')->insert(array_merge([
        'id' => $id,
        'email' => $id.'@example.test',
        'name' => 'Original Name',
    ], $overrides));

    return $id;
}

function makeAccountIS(string $clusterId, array $overrides = []): Account
{
    return Account::on('pgsql')->create(array_merge([
        'cluster_id' => $clusterId,
        'db_name' => TENANT_DB_IS,
        'slug' => 'acc-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => makeIdentityIS(),
        'status' => 'active',
    ], $overrides));
}

function makeMembershipIS(string $identityId, string $accountId, array $overrides = []): void
{
    DB::table('root_identity_accounts')->insert(array_merge([
        'id' => (string) Str::uuid(),
        'identity_id' => $identityId,
        'account_id' => $accountId,
        'status' => 'active',
    ], $overrides));
}

function accountRefIS(Account $account): AccountRef
{
    return new AccountRef(id: $account->id, slug: (string) $account->slug, status: $account->status, features: [], limits: []);
}

function migrateUsersTableIS(AccountRef $ref): void
{
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);

    $manager->runFor($ref, null, function (): void {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => realpath(CORE_MIGRATIONS_PATH_IS),
            '--realpath' => true,
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => realpath(TENANT_MIGRATIONS_PATH_IS),
            '--realpath' => true,
            '--force' => true,
        ]);
    });
}

it('rehashes only an active owned users row with its expected hash', function (): void {
    $account = makeAccountIS(makeClusterIS());
    $ref = accountRefIS($account);
    migrateUsersTableIS($ref);
    $identityId = makeIdentityIS();
    makeMembershipIS($identityId, $account->id);
    app(IdentitySyncer::class)->syncToAccount($identityId, $account->id);

    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor($ref, null, function () use ($identityId): void {
        $writer = app(IdentityPasswordRehasher::class);
        $columns = ['id' => 'id', 'email' => 'email', 'password' => 'password_hash', 'active' => 'is_active', 'deleted_at' => 'deleted_at'];
        DB::table('users')->where('id', $identityId)->update(['password_hash' => 'old']);

        expect(fn () => $writer->rehash($identityId, 'old', 'wrong', 'root_identities', $columns, null))
            ->toThrow(InvalidArgumentException::class);

        $writer->rehash($identityId, 'old', 'new', 'users', $columns, null);
        $writer->rehash($identityId, 'old', 'stale', 'users', $columns, null);
        expect(DB::table('users')->where('id', $identityId)->value('password_hash'))->toBe('new');

        DB::table('users')->where('id', $identityId)->update(['is_active' => false]);
        $writer->rehash($identityId, 'new', 'deactivated', 'users', $columns, null);
        expect(DB::table('users')->where('id', $identityId)->value('password_hash'))->toBe('new');
    });
});

it('syncToAccount() creates a denormalized users row keyed by the identity id (AC-16 а)', function (): void {
    $account = makeAccountIS(makeClusterIS());
    migrateUsersTableIS(accountRefIS($account));

    $identityId = makeIdentityIS(['email' => 'a@example.test', 'name' => 'Ada', 'phone' => '+7000', 'locale' => 'en']);
    makeMembershipIS($identityId, $account->id);

    app(IdentitySyncer::class)->syncToAccount($identityId, $account->id);

    $ref = accountRefIS($account);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $row = $manager->runFor($ref, null, fn (): ?object => DB::table('users')->where('id', $identityId)->first());

    expect($row)->not->toBeNull()
        ->and($row->email)->toBe('a@example.test')
        ->and($row->name)->toBe('Ada')
        ->and($row->phone)->toBe('+7000')
        ->and($row->locale)->toBe('en')
        ->and($row->is_active)->toBeTrue()
        ->and($row->synced_at)->not->toBeNull();

    $membership = DB::table('root_identity_accounts')->where('identity_id', $identityId)->first();
    expect($membership->sync_status)->toBe('synced');
});

it('syncToAccount() is idempotent — a second sync upserts, does not duplicate', function (): void {
    $account = makeAccountIS(makeClusterIS());
    migrateUsersTableIS(accountRefIS($account));

    $identityId = makeIdentityIS();
    makeMembershipIS($identityId, $account->id);

    $syncer = app(IdentitySyncer::class);
    $syncer->syncToAccount($identityId, $account->id);
    $syncer->syncToAccount($identityId, $account->id);

    $ref = accountRefIS($account);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $count = $manager->runFor($ref, null, fn (): int => DB::table('users')->where('id', $identityId)->count());

    expect($count)->toBe(1);
});

it('keeps distinct UUID identities separate when their phone values match', function (): void {
    $account = makeAccountIS(makeClusterIS());
    migrateUsersTableIS(accountRefIS($account));

    $firstIdentityId = makeIdentityIS(['phone' => '+70000000000']);
    $secondIdentityId = makeIdentityIS(['phone' => '+70000000000']);
    makeMembershipIS($firstIdentityId, $account->id);
    makeMembershipIS($secondIdentityId, $account->id);

    $syncer = app(IdentitySyncer::class);
    $syncer->syncToAccount($firstIdentityId, $account->id);
    $syncer->syncToAccount($secondIdentityId, $account->id);

    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $rows = $manager->runFor(accountRefIS($account), null, fn (): array => DB::table('users')
        ->whereIn('id', [$firstIdentityId, $secondIdentityId])
        ->orderBy('id')
        ->pluck('phone', 'id')
        ->all());

    expect($rows)->toHaveCount(2)
        ->and($rows[$firstIdentityId])->toBe('+70000000000')
        ->and($rows[$secondIdentityId])->toBe('+70000000000');
});

it('fanOut() reaches every account membership (AC-16 в)', function (): void {
    $clusterId = makeClusterIS();
    $accountA = makeAccountIS($clusterId);

    DB::statement('DROP DATABASE IF EXISTS acc_is_test_b');
    DB::statement('CREATE DATABASE acc_is_test_b');
    $accountB = makeAccountIS($clusterId, ['db_name' => 'acc_is_test_b']);

    migrateUsersTableIS(accountRefIS($accountA));
    migrateUsersTableIS(accountRefIS($accountB));

    $identityId = makeIdentityIS();
    makeMembershipIS($identityId, $accountA->id);
    makeMembershipIS($identityId, $accountB->id);

    app(IdentitySyncer::class)->fanOut($identityId);

    $statuses = DB::table('root_identity_accounts')->where('identity_id', $identityId)->pluck('sync_status', 'account_id');
    expect($statuses[$accountA->id])->toBe('synced')
        ->and($statuses[$accountB->id])->toBe('synced');

    DB::purge('tenant');
    DB::statement('DROP DATABASE IF EXISTS acc_is_test_b');
});

it('a failure syncing one account does not block another (AC-16 г)', function (): void {
    $clusterId = makeClusterIS();
    $accountA = makeAccountIS($clusterId);
    migrateUsersTableIS(accountRefIS($accountA));

    // A valid root_accounts row (passes the FK), but its physical database
    // was never created — runFor() throws mid-connection-switch and
    // syncToAccount() must fail-closed for THIS row only.
    $accountB = makeAccountIS($clusterId, ['db_name' => 'acc_is_test_missing']);

    $identityId = makeIdentityIS();
    makeMembershipIS($identityId, $accountA->id);
    makeMembershipIS($identityId, $accountB->id);

    app(IdentitySyncer::class)->fanOut($identityId);

    $statuses = DB::table('root_identity_accounts')->where('identity_id', $identityId)->pluck('sync_status', 'account_id')->values()->all();
    expect($statuses)->toContain('synced')
        ->and($statuses)->toContain('failed');
});

it('removing the membership row deactivates a projection without changing the profile clock', function (): void {
    $account = makeAccountIS(makeClusterIS());
    migrateUsersTableIS(accountRefIS($account));

    $identityId = makeIdentityIS();
    makeMembershipIS($identityId, $account->id);

    $syncer = app(IdentitySyncer::class);
    $syncer->syncToAccount($identityId, $account->id);

    DB::table('root_identity_accounts')->where('identity_id', $identityId)->where('account_id', $account->id)->delete();

    $syncer->syncToAccount($identityId, $account->id);

    $ref = accountRefIS($account);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $row = $manager->runFor($ref, null, fn (): ?object => DB::table('users')->where('id', $identityId)->first());

    expect($row)->not->toBeNull()
        ->and($row->is_active)->toBeFalse();
});

it('treats a non-active membership as inactive without overwriting a fresh profile', function (string $status): void {
    $account = makeAccountIS(makeClusterIS());
    migrateUsersTableIS(accountRefIS($account));

    $identityId = makeIdentityIS(['name' => 'Original']);
    makeMembershipIS($identityId, $account->id);

    $syncer = app(IdentitySyncer::class);
    $syncer->syncToAccount($identityId, $account->id);

    $ref = accountRefIS($account);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor($ref, null, function () use ($identityId): void {
        DB::table('users')->where('id', $identityId)->update([
            'name' => 'Locally newer',
            'synced_at' => now()->addMinute(),
        ]);
    });
    DB::table('root_identity_accounts')
        ->where('identity_id', $identityId)
        ->where('account_id', $account->id)
        ->update(['status' => $status]);

    $syncer->syncToAccount($identityId, $account->id);

    $row = $manager->runFor($ref, null, fn (): ?object => DB::table('users')->where('id', $identityId)->first());

    expect($row->name)->toBe('Locally newer')
        ->and($row->is_active)->toBeFalse();
})->with(['blocked', 'invited']);

it('reactivates the same UUID after an active regrant without touching a fresh profile', function (): void {
    $account = makeAccountIS(makeClusterIS());
    migrateUsersTableIS(accountRefIS($account));

    $identityId = makeIdentityIS(['name' => 'Original']);
    makeMembershipIS($identityId, $account->id, ['status' => 'blocked']);

    $syncer = app(IdentitySyncer::class);
    $syncer->syncToAccount($identityId, $account->id);

    $ref = accountRefIS($account);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor($ref, null, function () use ($identityId): void {
        DB::table('users')->where('id', $identityId)->update([
            'name' => 'Locally newer',
            'synced_at' => now()->addMinute(),
        ]);
    });
    DB::table('root_identity_accounts')
        ->where('identity_id', $identityId)
        ->where('account_id', $account->id)
        ->update(['status' => 'active']);

    $syncer->syncToAccount($identityId, $account->id);

    $row = $manager->runFor($ref, null, fn (): ?object => DB::table('users')->where('id', $identityId)->first());

    expect($row->id)->toBe($identityId)
        ->and($row->name)->toBe('Locally newer')
        ->and($row->is_active)->toBeTrue();
});

it('emits UserSynced on a real sync', function (): void {
    Event::fake([UserSynced::class]);

    $account = makeAccountIS(makeClusterIS());
    migrateUsersTableIS(accountRefIS($account));

    $identityId = makeIdentityIS();
    makeMembershipIS($identityId, $account->id);

    app(IdentitySyncer::class)->syncToAccount($identityId, $account->id);

    Event::assertDispatched(UserSynced::class, fn (UserSynced $event): bool => $event->identityId === $identityId && $event->accountId === $account->id);
});

it('the version-guard skips a stale write once the projection is already newer (premortem C3/C6)', function (): void {
    $account = makeAccountIS(makeClusterIS());
    migrateUsersTableIS(accountRefIS($account));

    $identityId = makeIdentityIS(['name' => 'First']);
    makeMembershipIS($identityId, $account->id);

    $syncer = app(IdentitySyncer::class);
    $syncer->syncToAccount($identityId, $account->id);

    $ref = accountRefIS($account);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);

    // Simulate the projection already having a newer source version than the
    // (unchanged) source identity's updated_at — a second sync must no-op.
    $manager->runFor($ref, null, function () use ($identityId): void {
        DB::table('users')->where('id', $identityId)->update(['identity_source_updated_at' => now()->addMinute(), 'name' => 'Untouched']);
    });

    $syncer->syncToAccount($identityId, $account->id);

    $row = $manager->runFor($ref, null, fn (): ?object => DB::table('users')->where('id', $identityId)->first());
    expect($row->name)->toBe('Untouched');
});

it('preserves source ordering independently of delivery time', function (bool $deliverNewerFirst): void {
    $account = makeAccountIS(makeClusterIS());
    migrateUsersTableIS(accountRefIS($account));
    $identity = makeIdentityIS(['name' => 'v1', 'updated_at' => '2026-09-01 10:00:00.123456+00']);
    makeMembershipIS($identity, $account->id);
    $syncer = app(IdentitySyncer::class);
    config(['database.connections.identity_writer_test' => config('database.connections.pgsql')]);
    $writer = DB::connection('identity_writer_test');
    $injected = false;
    DB::listen(function ($event) use ($writer, $identity, $account, $syncer, $deliverNewerFirst, &$injected): void {
        if (! $injected && str_starts_with($event->sql, 'select * from "root_identities"')) {
            $injected = true;
            $writer->table('root_identities')->where('id', $identity)
                ->update(['name' => 'v2', 'updated_at' => '2026-09-01 10:00:00.654321+00']);

            if ($deliverNewerFirst) {
                $syncer->syncToAccount($identity, $account->id);
            }
        }
    });
    $syncer->syncToAccount($identity, $account->id);

    if (! $deliverNewerFirst) {
        $syncer->syncToAccount($identity, $account->id);
    }
    $row = app(TenancyManager::class)->runFor(accountRefIS($account), null, fn () => DB::table('users')->where('id', $identity)->first());
    expect($injected)->toBeTrue()->and($row->name)->toBe('v2')
        ->and($row->identity_source_updated_at)->toContain('10:00:00.654321');
    DB::purge('identity_writer_test');
})->with([false, true]);

it('corex:tenancy:reconcile-identities re-syncs failed and stale rows (AC-16 з)', function (): void {
    $account = makeAccountIS(makeClusterIS());
    migrateUsersTableIS(accountRefIS($account));

    $identityId = makeIdentityIS();
    makeMembershipIS($identityId, $account->id, ['sync_status' => 'failed']);

    $this->artisan('corex:tenancy:reconcile-identities')->assertExitCode(0);

    $ref = accountRefIS($account);
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $row = $manager->runFor($ref, null, fn (): ?object => DB::table('users')->where('id', $identityId)->first());

    expect($row)->not->toBeNull();

    $membership = DB::table('root_identity_accounts')->where('identity_id', $identityId)->first();
    expect($membership->sync_status)->toBe('synced');
});
