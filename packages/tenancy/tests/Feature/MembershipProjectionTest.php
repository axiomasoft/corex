<?php

declare(strict_types=1);

use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\CentralApiClient;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Http\Middleware\RequireActiveMembership;
use CoreX\Tenancy\Membership\Projection\ApplyMembershipProjection;
use CoreX\Tenancy\Membership\Transport\CentralUnavailable;
use CoreX\Tenancy\Membership\Transport\MembershipQuery;
use CoreX\Tenancy\Membership\Transport\MembershipSnapshot;
use CoreX\Tenancy\Membership\Transport\MembershipState;
use CoreX\Tenancy\Membership\Transport\RevokeMembershipReceipt;
use CoreX\Tenancy\Membership\Transport\RevokeMembershipRequest;
use CoreX\Tenancy\Models\Account;
use CoreX\Tenancy\Tests\Stubs\StubIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

const ROOT_MIGRATIONS_PATH_MP = __DIR__.'/../../database/migrations/root';
const TENANT_MIGRATIONS_PATH_MP = __DIR__.'/../../database/migrations/tenant';
const CORE_MIGRATIONS_PATH_MP = __DIR__.'/../../vendor/corex/core/database/migrations/pg';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(ROOT_MIGRATIONS_PATH_MP),
        '--realpath' => true,
        '--force' => true,
    ])->run();
});

afterEach(function (): void {
    DB::purge('pgsql');
    DB::purge('tenant');

    foreach ($GLOBALS['membership_projection_databases'] ?? [] as $database) {
        DB::statement('DROP DATABASE IF EXISTS '.$database);
    }
    $GLOBALS['membership_projection_databases'] = [];
});

function membershipProjectionAccount(): Account
{
    $database = 'acc_mp_'.Str::lower(Str::random(8));
    $GLOBALS['membership_projection_databases'][] = $database;
    DB::statement('CREATE DATABASE '.$database);

    $clusterId = (string) Str::uuid();
    DB::table('root_clusters')->insert([
        'id' => $clusterId,
        'name' => 'cluster-'.$clusterId,
        'pg_host_writer' => 'writer.local',
        'pgbouncer_host' => 'pgbouncer.local',
        'app_db_user' => 'corex',
    ]);
    $ownerId = (string) Str::uuid();
    DB::table('root_identities')->insert(['id' => $ownerId, 'email' => $ownerId.'@example.test']);

    return Account::on('pgsql')->create([
        'cluster_id' => $clusterId,
        'db_name' => $database,
        'slug' => 'account-'.Str::random(8),
        'template_version' => 1,
        'embedding_model' => 'bge-m3',
        'embedding_dim' => 1024,
        'owner_identity_id' => $ownerId,
        'status' => 'active',
    ]);
}

function membershipProjectionRef(Account $account): AccountRef
{
    return new AccountRef(
        id: $account->id,
        slug: (string) $account->slug,
        status: $account->status,
        features: [],
        limits: [],
    );
}

function migrateMembershipProjectionTenant(Account $account): void
{
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor(membershipProjectionRef($account), null, function (): void {
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => realpath(CORE_MIGRATIONS_PATH_MP),
            '--realpath' => true,
            '--force' => true,
        ]);
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => realpath(TENANT_MIGRATIONS_PATH_MP),
            '--realpath' => true,
            '--force' => true,
        ]);
    });
}

function membershipProjectionSnapshot(MembershipQuery $query, MembershipState $state): MembershipSnapshot
{
    return new MembershipSnapshot(
        profileVersion: 'corex-membership/1',
        requestId: $query->requestId,
        identityId: $query->identityId,
        accountId: $query->accountId,
        product: $query->product,
        membershipId: $state === MembershipState::Active ? (string) Str::uuid() : null,
        state: $state,
        revision: '1',
        revokeGeneration: '0',
        readStartedAt: new DateTimeImmutable('2026-09-13T00:00:00Z'),
        observedAt: new DateTimeImmutable('2026-09-13T00:00:01Z'),
    );
}

function membershipProjectionReceipt(RevokeMembershipRequest $request): RevokeMembershipReceipt
{
    return new RevokeMembershipReceipt(
        profileVersion: 'corex-membership/1',
        requestId: $request->requestId,
        identityId: $request->identityId,
        accountId: $request->accountId,
        product: $request->product,
        commitRef: 'test:receipt',
        revision: '1',
        revokeGeneration: '0',
        committedAt: new DateTimeImmutable('2026-09-13T00:00:01Z'),
    );
}

function refreshMembershipProjection(ApplyMembershipProjection $projection, string $identityId, string $accountId): void
{
    $projection->refresh(
        identityId: $identityId,
        accountId: $accountId,
        product: 'projection:test',
        requestId: (string) Str::uuid(),
    );
}

it('deactivates and clears sessions only in the target account', function (): void {
    $accountA = membershipProjectionAccount();
    $accountB = membershipProjectionAccount();
    migrateMembershipProjectionTenant($accountA);
    migrateMembershipProjectionTenant($accountB);

    $identityId = (string) Str::uuid();
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    foreach ([$accountA, $accountB] as $account) {
        $manager->runFor(membershipProjectionRef($account), null, function () use ($identityId): void {
            DB::table('users')->insert([
                'id' => $identityId,
                'email' => $identityId.'@example.test',
                'name' => 'User',
                'locale' => 'en',
                'is_active' => true,
                'settings' => '{}',
            ]);
            DB::table('sessions')->insert([
                'id' => Str::random(40),
                'user_id' => $identityId,
                'payload' => 'payload',
                'last_activity' => time(),
            ]);
        });
    }

    $client = new class($identityId, $accountA->id) implements CentralApiClient
    {
        public function __construct(private readonly string $identityId, private readonly string $accountId) {}

        public function revokeMembership(RevokeMembershipRequest $request): RevokeMembershipReceipt
        {
            return membershipProjectionReceipt($request);
        }

        public function membershipFor(MembershipQuery $query): MembershipSnapshot
        {
            return membershipProjectionSnapshot(query: $query, state: MembershipState::Revoked);
        }
    };
    $projection = new ApplyMembershipProjection(
        centralConnection: 'pgsql',
        centralApi: $client,
        tenancy: $manager,
    );

    refreshMembershipProjection(projection: $projection, identityId: $identityId, accountId: $accountA->id);

    $a = $manager->runFor(membershipProjectionRef($accountA), null, fn (): array => [
        DB::table('users')->where('id', $identityId)->value('is_active'),
        DB::table('sessions')->where('user_id', $identityId)->count(),
    ]);
    $b = $manager->runFor(membershipProjectionRef($accountB), null, fn (): array => [
        DB::table('users')->where('id', $identityId)->value('is_active'),
        DB::table('sessions')->where('user_id', $identityId)->count(),
    ]);

    expect($a)->toBe([false, 0])
        ->and($b)->toBe([true, 1]);
});

it('does not restore a deleted session when an active membership is regranted', function (): void {
    $account = membershipProjectionAccount();
    migrateMembershipProjectionTenant($account);

    $identityId = (string) Str::uuid();
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor(membershipProjectionRef($account), null, function () use ($identityId): void {
        DB::table('users')->insert([
            'id' => $identityId,
            'email' => $identityId.'@example.test',
            'name' => 'User',
            'locale' => 'en',
            'is_active' => true,
            'settings' => '{}',
        ]);
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $identityId,
            'payload' => 'payload',
            'last_activity' => time(),
        ]);
    });

    $client = new class($identityId, $account->id) implements CentralApiClient
    {
        public bool $active = false;

        public function __construct(private readonly string $identityId, private readonly string $accountId) {}

        public function revokeMembership(RevokeMembershipRequest $request): RevokeMembershipReceipt
        {
            return membershipProjectionReceipt($request);
        }

        public function membershipFor(MembershipQuery $query): MembershipSnapshot
        {
            return membershipProjectionSnapshot(query: $query, state: $this->active ? MembershipState::Active : MembershipState::Revoked);
        }
    };
    $projection = new ApplyMembershipProjection(
        centralConnection: 'pgsql',
        centralApi: $client,
        tenancy: $manager,
    );

    refreshMembershipProjection(projection: $projection, identityId: $identityId, accountId: $account->id);
    $client->active = true;
    refreshMembershipProjection(projection: $projection, identityId: $identityId, accountId: $account->id);

    $result = $manager->runFor(membershipProjectionRef($account), null, fn (): array => [
        DB::table('users')->where('id', $identityId)->value('is_active'),
        DB::table('sessions')->where('user_id', $identityId)->count(),
    ]);

    expect($result)->toBe([true, 0]);
});

it('does not let a delayed grant hint re-enable a revoked membership', function (): void {
    $account = membershipProjectionAccount();
    migrateMembershipProjectionTenant($account);

    $identityId = (string) Str::uuid();
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor(membershipProjectionRef($account), null, function () use ($identityId): void {
        DB::table('users')->insert([
            'id' => $identityId,
            'email' => $identityId.'@example.test',
            'name' => 'User',
            'locale' => 'en',
            'is_active' => true,
            'settings' => '{}',
        ]);
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $identityId,
            'payload' => 'payload',
            'last_activity' => time(),
        ]);
    });

    $client = new class($identityId, $account->id) implements CentralApiClient
    {
        public int $queries = 0;

        public function __construct(private readonly string $identityId, private readonly string $accountId) {}

        public function revokeMembership(RevokeMembershipRequest $request): RevokeMembershipReceipt
        {
            return membershipProjectionReceipt($request);
        }

        public function membershipFor(MembershipQuery $query): MembershipSnapshot
        {
            $this->queries++;

            return membershipProjectionSnapshot(query: $query, state: MembershipState::Revoked);
        }
    };
    $projection = new ApplyMembershipProjection(
        centralConnection: 'pgsql',
        centralApi: $client,
        tenancy: $manager,
    );

    refreshMembershipProjection(projection: $projection, identityId: $identityId, accountId: $account->id);
    refreshMembershipProjection(projection: $projection, identityId: $identityId, accountId: $account->id);

    $result = $manager->runFor(membershipProjectionRef($account), null, fn (): array => [
        DB::table('users')->where('id', $identityId)->value('is_active'),
        DB::table('sessions')->where('user_id', $identityId)->count(),
    ]);

    expect($client->queries)->toBe(2)
        ->and($result)->toBe([false, 0]);
});

it('fails closed and clears target sessions when central authority is unavailable', function (): void {
    $account = membershipProjectionAccount();
    migrateMembershipProjectionTenant($account);

    $identityId = (string) Str::uuid();
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor(membershipProjectionRef($account), null, function () use ($identityId): void {
        DB::table('users')->insert([
            'id' => $identityId,
            'email' => $identityId.'@example.test',
            'name' => 'User',
            'locale' => 'en',
            'is_active' => true,
            'settings' => '{}',
        ]);
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $identityId,
            'payload' => 'payload',
            'last_activity' => time(),
        ]);
    });

    $client = new class implements CentralApiClient
    {
        public function revokeMembership(RevokeMembershipRequest $request): RevokeMembershipReceipt
        {
            return membershipProjectionReceipt($request);
        }

        public function membershipFor(MembershipQuery $query): MembershipSnapshot
        {
            throw new CentralUnavailable('Central API is unavailable.');
        }
    };
    $projection = new ApplyMembershipProjection(
        centralConnection: 'pgsql',
        centralApi: $client,
        tenancy: $manager,
    );

    refreshMembershipProjection(projection: $projection, identityId: $identityId, accountId: $account->id);

    $result = $manager->runFor(membershipProjectionRef($account), null, fn (): array => [
        DB::table('users')->where('id', $identityId)->value('is_active'),
        DB::table('sessions')->where('user_id', $identityId)->count(),
    ]);

    expect($result)->toBe([false, 0]);
});

it('fails closed when the central client raises an unexpected read failure', function (): void {
    $account = membershipProjectionAccount();
    migrateMembershipProjectionTenant($account);

    $identityId = (string) Str::uuid();
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor(membershipProjectionRef($account), null, function () use ($identityId): void {
        DB::table('users')->insert([
            'id' => $identityId,
            'email' => $identityId.'@example.test',
            'name' => 'User',
            'locale' => 'en',
            'is_active' => true,
            'settings' => '{}',
        ]);
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $identityId,
            'payload' => 'payload',
            'last_activity' => time(),
        ]);
    });

    $client = new class implements CentralApiClient
    {
        public function revokeMembership(RevokeMembershipRequest $request): RevokeMembershipReceipt
        {
            return membershipProjectionReceipt($request);
        }

        public function membershipFor(MembershipQuery $query): MembershipSnapshot
        {
            throw new RuntimeException('Unexpected central client failure.');
        }
    };
    $projection = new ApplyMembershipProjection(
        centralConnection: 'pgsql',
        centralApi: $client,
        tenancy: $manager,
    );

    refreshMembershipProjection(projection: $projection, identityId: $identityId, accountId: $account->id);

    $result = $manager->runFor(membershipProjectionRef($account), null, fn (): array => [
        DB::table('users')->where('id', $identityId)->value('is_active'),
        DB::table('sessions')->where('user_id', $identityId)->count(),
    ]);

    expect($result)->toBe([false, 0]);
});

it('denies the next request and logs out an inactive local projection', function (): void {
    $account = membershipProjectionAccount();
    migrateMembershipProjectionTenant($account);

    $identityId = (string) Str::uuid();
    /** @var TenancyManager $manager */
    $manager = app(TenancyManager::class);
    $manager->runFor(membershipProjectionRef($account), null, function () use ($identityId): void {
        DB::table('users')->insert([
            'id' => $identityId,
            'email' => $identityId.'@example.test',
            'name' => 'User',
            'locale' => 'en',
            'is_active' => false,
            'settings' => '{}',
        ]);
    });

    $this->actingAs(new StubIdentity($identityId), 'web');
    $session = new Store('membership-projection', new ArraySessionHandler(120));
    $session->start();
    $request = Request::create('http://tenant.example.test/dashboard', 'GET');
    $request->setLaravelSession($session);

    $status = $manager->runFor(membershipProjectionRef($account), null, function () use ($request): int {
        try {
            (new RequireActiveMembership)->handle($request, fn (): Response => new Response('ok'));
        } catch (HttpException $exception) {
            return $exception->getStatusCode();
        }

        return 200;
    });

    expect($status)->toBe(403)
        ->and(Auth::guard('web')->check())->toBeFalse();
});
