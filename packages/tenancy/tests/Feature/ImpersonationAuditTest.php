<?php

declare(strict_types=1);

use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Contracts\SynchronousAuditWriter;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Config\Laravel\CoreOptionsParser;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Tenancy\Impersonation\Contracts\ImpersonationAuditSink;
use CoreX\Tenancy\Impersonation\Internal\DualImpersonationAuditSink;
use CoreX\Tenancy\Internal\DangerousModesCheck;
use CoreX\Tenancy\Laravel\Support\ImpersonationPolicy;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

beforeAll(function (): void {
    TestCase::$oidcEnabled = true;
    putenv('COREX_TENANCY_OIDC_IMPERSONATION_ENABLED=true');
});

afterAll(function (): void {
    TestCase::$oidcEnabled = false;
    putenv('COREX_TENANCY_OIDC_IMPERSONATION_ENABLED=false');
});

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    test()->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');
});

afterEach(function (): void {
    endTenantContext();
    DB::purge('tenant');
    DB::purge('pgsql');
    foreach ($GLOBALS['__im_test_dbs'] ?? [] as $db) {
        DB::statement('DROP DATABASE IF EXISTS '.$db);
    }
    $GLOBALS['__im_test_dbs'] = [];
});

it('uses the stock writer to commit central and tenant audit before login with optional audit disabled and async queue', function (): void {
    config(['corex.audit.enabled' => false, 'queue.default' => 'database']);
    $account = initAccountWithTenantIM();
    seedTenantUserIM($account, $account->owner_identity_id);
    $staffId = makeStaffIdentityIM();
    $grantId = makeGrantIM($staffId, $account->id);

    expect(app(ImpersonationService::class))->toBeInstanceOf(ImpersonationService::class)
        ->and(app(ImpersonationAuditSink::class))->toBeInstanceOf(DualImpersonationAuditSink::class)
        ->and(app(SynchronousAuditWriter::class))->toBeInstanceOf(SynchronousAuditWriter::class);
    expect(app(AuditLogger::class))->toBeInstanceOf(AuditLogger::class);
    app(ImpersonationService::class)->start($grantId, $staffId);

    $central = DB::connection('pgsql')->table('root_account_events')->where('event', 'impersonation.started')->sole();
    $tenant = DB::connection('tenant')->table('sys_audit_log')->where('action', 'impersonation.started')->sole();
    $payload = json_decode((string) $central->payload, true, 512, JSON_THROW_ON_ERROR);
    $context = json_decode((string) $tenant->context, true, 512, JSON_THROW_ON_ERROR);
    expect($payload['profile'])->toBe('standard')
        ->and($payload['expires_at'])->not->toBeNull()
        ->and($context['grant_id'])->toBe($grantId)
        ->and($tenant->actor_id)->toBe($staffId)
        ->and(Auth::guard('web')->check())->toBeTrue();

    app(ImpersonationService::class)->stop();
    expect(DB::connection('pgsql')->table('root_account_events')->where('event', 'impersonation.ended')->count())->toBe(1)
        ->and(DB::connection('tenant')->table('sys_audit_log')->where('action', 'impersonation.ended')->count())->toBe(1);
});

it('keeps committed central intent and a consumed grant when the tenant writer fails before login', function (): void {
    $account = initAccountWithTenantIM();
    seedTenantUserIM($account, $account->owner_identity_id);
    $staffId = makeStaffIdentityIM();
    $grantId = makeGrantIM($staffId, $account->id);
    app()->forgetScopedInstances();
    app()->instance(SynchronousAuditWriter::class, new class implements SynchronousAuditWriter
    {
        public function record(AuditEntry $entry, string $connectionName): void
        {
            throw new RuntimeException('tenant mirror failed');
        }
    });
    expect(fn () => app(ImpersonationService::class)->start($grantId, $staffId))->toThrow(RuntimeException::class);
    expect(grantRowIM($grantId)->used_at)->not->toBeNull()
        ->and(DB::connection('pgsql')->table('root_account_events')->where('event', 'impersonation.started')->count())->toBe(1)
        ->and(Auth::guard('web')->check())->toBeFalse()
        ->and(session('corex.impersonation.grant_id'))->toBeNull();
});

it('refuses login and consumes the grant when the central audit insert fails', function (): void {
    $account = initAccountWithTenantIM();
    seedTenantUserIM($account, $account->owner_identity_id);
    $staffId = makeStaffIdentityIM();
    $grantId = makeGrantIM($staffId, $account->id);
    DB::connection('pgsql')->statement('DROP TABLE root_account_events');

    expect(fn () => app(ImpersonationService::class)->start($grantId, $staffId))->toThrow(QueryException::class);
    expect(grantRowIM($grantId)->used_at)->not->toBeNull()
        ->and(DB::connection('tenant')->table('sys_audit_log')->count())->toBe(0)
        ->and(Auth::guard('web')->check())->toBeFalse();
});

it('rejects an ambient tenant transaction after consuming the grant', function (): void {
    $account = initAccountWithTenantIM();
    seedTenantUserIM($account, $account->owner_identity_id);
    $staffId = makeStaffIdentityIM();
    $grantId = makeGrantIM($staffId, $account->id);
    $tenant = DB::connection('tenant');
    $tenant->beginTransaction();

    try {
        expect(fn () => app(ImpersonationService::class)->start($grantId, $staffId))->toThrow(RuntimeException::class);
        expect(grantRowIM($grantId)->used_at)->not->toBeNull()
            ->and(DB::connection('pgsql')->table('root_account_events')->where('event', 'impersonation.started')->count())->toBe(0)
            ->and(Auth::guard('web')->check())->toBeFalse();
    } finally {
        $tenant->rollBack();
    }
});

it('omits tenant mirror in dev and requires production acknowledgement', function (): void {
    config(['corex-tenancy.impersonation.profile' => 'dev']);
    app()->forgetInstance(TenancyOptions::class);
    app()->forgetInstance(ImpersonationPolicy::class);
    app()->forgetScopedInstances();
    $account = initAccountWithTenantIM();
    seedTenantUserIM($account, $account->owner_identity_id);
    $staffId = makeStaffIdentityIM();
    $grantId = makeGrantIM($staffId, $account->id, ['expires_at' => now()->addHours(4)]);
    app(ImpersonationService::class)->start($grantId, $staffId);

    expect(DB::connection('pgsql')->table('root_account_events')->where('event', 'impersonation.started')->count())->toBe(1)
        ->and(DB::connection('tenant')->table('sys_audit_log')->count())->toBe(0);

    $options = app(TenancyOptions::class);
    $path = 'corex-tenancy.impersonation.profile';
    expect((new DangerousModesCheck($options, CoreOptionsParser::fromArray([])))->violations(true))->toContain($path.': dangerous mode is not acknowledged for production')
        ->and((new DangerousModesCheck($options, CoreOptionsParser::fromArray(['acknowledged_dangerous_modes' => [$path]])))->violations(true))->toBe([]);
});
