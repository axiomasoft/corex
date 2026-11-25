<?php

declare(strict_types=1);

use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Tenancy\Runtime\Contracts\TenantBindingAuthority;
use CoreX\Tenancy\Runtime\Internal\TenantPhysicalBindingVerifier;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tenant\Contracts\Runtime\TenantRuntime;
use Tenant\Data\Context\ContextState;
use Tenant\Data\Context\IsolationContext;
use Tenant\Data\Context\IsolationIdentity;
use Tenant\Data\Context\ProfileRef;
use Tenant\Data\Verification\BindingExpectation;
use Tenant\Data\Verification\VerifiedBinding;

it('resolves a missing worker account only through the central directory', function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');
    expect(fn () => resolve(WorkScopeDirectory::class)->account((string) Str::uuid()))->toThrow(ScopeContextMismatch::class)
        ->and(DB::getDefaultConnection())->toBe('pgsql');
});

it('compares writer and replica markers to the active Tenant owner proof', function (): void {
    $scope = new PhysicalBinding('deploy', 'resource:1', 7, 'account:1', str_repeat('a', 64));
    $context = new TenantContext(new AccountRef('a', 'a', 'active', [], []));
    $expectation = new BindingExpectation('resource:1', 'physical:1', 'route:opaque');
    $proof = new VerifiedBinding($expectation, 'tenant-postgres', 'recovered:opaque');
    $owner = Mockery::mock(TenantBindingAuthority::class);
    $owner->shouldReceive('verify')->with($scope, $context)->twice()->andReturn($proof);
    $runtime = Mockery::mock(TenantRuntime::class);
    $runtime->shouldReceive('current')->andReturn(ContextState::active(new IsolationContext(
        'tenant', new IsolationIdentity('cloud', 'a'), new ProfileRef('pg', 1), $expectation,
    )));
    $marker = ['protocol' => 'tenant.binding/1', 'owner_id' => 'tenant-postgres', 'resource_ref' => 'resource:1',
        'incarnation' => 'physical:1', 'recovery_generation' => 'recovered:opaque'];
    $connection = Mockery::mock(ConnectionInterface::class);
    $connection->shouldReceive('select')->with(Mockery::type('string'), [], false)->twice()->andReturn([(object) $marker]);
    $connection->shouldReceive('select')->with(Mockery::type('string'), [], true)->twice()
        ->andReturn([(object) $marker], [(object) [...$marker, 'resource_ref' => 'other-resource']]);
    $verifier = new TenantPhysicalBindingVerifier($runtime, $owner);
    $verifier->verify($scope, $context, $connection);
    expect(fn () => $verifier->verify($scope, $context, $connection))->toThrow(ScopeStale::class);
});
