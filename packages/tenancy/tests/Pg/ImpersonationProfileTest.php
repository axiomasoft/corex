<?php

declare(strict_types=1);

use CoreX\Tenancy\Impersonation\Data\GrantClaim;
use CoreX\Tenancy\Impersonation\Enums\ImpersonationProfile;
use CoreX\Tenancy\Impersonation\Internal\DatabaseImpersonationGrantStore;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(TestCase::class);

it('enforces all 27 grant profile matrix cells in the atomic claim', function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    test()->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');

    $central = DB::connection('pgsql');
    $staffId = (string) Str::uuid();
    $approverId = (string) Str::uuid();
    $nonStaffId = (string) Str::uuid();
    $ownerId = (string) Str::uuid();
    foreach ([$staffId => true, $approverId => true, $nonStaffId => false, $ownerId => false] as $id => $staff) {
        $central->table('root_identities')->insert(['id' => $id, 'email' => $id.'@test.invalid', 'is_staff' => $staff]);
    }

    $clusterId = (string) Str::uuid();
    $accountId = (string) Str::uuid();
    $central->table('root_clusters')->insert([
        'id' => $clusterId, 'name' => 'profile-test', 'pg_host_writer' => 'localhost',
        'pgbouncer_host' => 'localhost', 'app_db_user' => 'corex',
    ]);
    $central->table('root_accounts')->insert([
        'id' => $accountId, 'cluster_id' => $clusterId, 'db_name' => 'profile_test',
        'status' => 'active', 'slug' => 'profile-test', 'owner_identity_id' => $ownerId,
        'template_version' => 1, 'embedding_model' => 'bge-m3', 'embedding_dim' => 1024,
    ]);

    $cases = [
        ['minutes' => 45, 'approver' => null, 'staff' => true, 'reason' => 'ticket', 'accepted' => [true, true, false]],
        ['minutes' => 45, 'approver' => $approverId, 'staff' => true, 'reason' => 'ticket', 'accepted' => [true, true, false]],
        ['minutes' => 20, 'approver' => $approverId, 'staff' => true, 'reason' => 'ticket', 'accepted' => [true, true, true]],
        ['minutes' => 20, 'approver' => $staffId, 'staff' => true, 'reason' => 'ticket', 'accepted' => [true, true, false]],
        ['minutes' => 20, 'approver' => $nonStaffId, 'staff' => true, 'reason' => 'ticket', 'accepted' => [true, true, false]],
        ['minutes' => 20, 'approver' => null, 'staff' => true, 'reason' => 'ticket', 'accepted' => [true, true, false]],
        ['minutes' => 20, 'approver' => $approverId, 'staff' => false, 'reason' => 'ticket', 'accepted' => [false, false, false]],
        ['minutes' => 120, 'approver' => null, 'staff' => true, 'reason' => 'ticket', 'accepted' => [true, false, false]],
        ['minutes' => 20, 'approver' => null, 'staff' => true, 'reason' => '', 'accepted' => [false, false, false]],
    ];
    $store = new DatabaseImpersonationGrantStore(connections: app(ConnectionResolverInterface::class), centralConnection: 'pgsql');
    $assertions = 0;

    foreach ($cases as $case) {
        $central->table('root_identities')->where('id', $staffId)->update(['is_staff' => $case['staff']]);
        foreach (ImpersonationProfile::cases() as $index => $profile) {
            $now = now()->toDateTimeImmutable();
            $grantId = (string) Str::uuid();
            $central->table('root_impersonation_grants')->insert([
                'id' => $grantId, 'staff_identity_id' => $staffId, 'account_id' => $accountId,
                'reason' => $case['reason'], 'approved_by' => $case['approver'],
                'created_at' => $now, 'expires_at' => $now->modify('+'.$case['minutes'].' minutes'),
            ]);
            $claimed = $store->claim(new GrantClaim(grantId: $grantId, staffIdentityId: $staffId, accountId: $accountId, profile: $profile, now: $now));
            expect($claimed !== null)->toBe($case['accepted'][$index]);
            expect($central->table('root_impersonation_grants')->where('id', $grantId)->value('used_at') !== null)
                ->toBe($case['accepted'][$index]);
            $assertions++;
        }
    }

    expect($assertions)->toBe(27);
})->group('pg');
