<?php

declare(strict_types=1);

use CoreX\Contracts\TenantStorage;
use CoreX\Support\PackageRegistrar;
use CoreX\Tenancy\Account\Lifecycle\AccountLifecycle;
use CoreX\Tenancy\Authentication\Oidc\IdTokenVerifier;
use CoreX\Tenancy\Authentication\Oidc\JwksKeySet;
use CoreX\Tenancy\Authentication\Oidc\OidcClient;
use CoreX\Tenancy\Authentication\Oidc\OidcDiscovery;
use CoreX\Tenancy\Contracts\ImpersonationService;
use CoreX\Tenancy\Contracts\PrincipalWorkspaceResolver;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\Contracts\TenancyManager;
use CoreX\Tenancy\Contracts\TenantContextResolver;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use CoreX\Tenancy\Contracts\TenantDatabaseLifecycle;
use CoreX\Tenancy\DomainTrust\DomainTrustCoordinator;
use CoreX\Tenancy\DomainTrust\DomainTrustStateWriter;
use CoreX\Tenancy\Http\Controllers\LogoutController;
use CoreX\Tenancy\Http\Controllers\OidcLoginController;
use CoreX\Tenancy\Identity\DatabaseIdentitySyncer;
use CoreX\Tenancy\Identity\IdentitySyncer;
use CoreX\Tenancy\Impersonation\DatabaseImpersonationService;
use CoreX\Tenancy\Laravel\AuthenticationRegistrar;
use CoreX\Tenancy\Laravel\DatabaseRegistrar;
use CoreX\Tenancy\Laravel\DomainTrustRegistrar;
use CoreX\Tenancy\Laravel\IdentityRegistrar;
use CoreX\Tenancy\Laravel\ProvisioningRegistrar;
use CoreX\Tenancy\Laravel\RuntimeRegistrar;
use CoreX\Tenancy\Laravel\WakeupRegistrar;
use CoreX\Tenancy\Laravel\WorkspaceRegistrar;
use CoreX\Tenancy\NullImpersonationService;
use CoreX\Tenancy\Provisioning\Database\SchemaCatalogueSerializer;
use CoreX\Tenancy\Provisioning\Database\TemplateIntegrityLock;
use CoreX\Tenancy\Provisioning\Database\TemplateIntegrityVerifier as DatabaseTemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Migrations\MigrationWaveOrchestrator;
use CoreX\Tenancy\Provisioning\PendingPool;
use CoreX\Tenancy\Provisioning\StanclTenantDatabaseLifecycle;
use CoreX\Tenancy\Provisioning\StanclTenantDatabaseProvisioner;
use CoreX\Tenancy\Runtime\ConnectionGuardedTenantContextRestorer;
use CoreX\Tenancy\Runtime\Storage\ContextTenantStorage;
use CoreX\Tenancy\StanclTenancyManager;
use CoreX\Tenancy\Support\TenancyOptions;
use CoreX\Tenancy\TenancyManagerContextResolver;
use CoreX\Tenancy\Tests\Concerns\AssertsContainerBindings;
use CoreX\Tenancy\Tests\TestCase;
use CoreX\Tenancy\Wakeup\Internal\RootRegistryWakeupHint;
use CoreX\Tenancy\Wakeup\Internal\RootWakeupReconciler;
use CoreX\Tenancy\Workspace\DatabaseWorkspaceResolver;
use CoreX\Tenancy\Workspace\WorkspaceResolver;

uses(AssertsContainerBindings::class);
uses()->group('pg');

beforeEach(function (): void {
    if (! $this->app->bound(ImpersonationService::class)) {
        $this->app->singleton(ImpersonationService::class, NullImpersonationService::class);
    }
});

it('keeps Runtime register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    $this->assertSingletonBinding($app, StanclTenancyManager::class, StanclTenancyManager::class);
    $this->assertSingletonBinding($app, TenancyManager::class, StanclTenancyManager::class);
    $this->assertSingletonBinding(
        $app,
        TenantContextResolver::class,
        TenancyManagerContextResolver::class,
    );
    $this->assertScopedBinding($app, TenantStorage::class, ContextTenantStorage::class);
    $this->assertScopedBinding(
        $app,
        TenantContextRestorer::class,
        ConnectionGuardedTenantContextRestorer::class,
    );

    foreach ($app->make(TenancyOptions::class)->bootstrapperOrder as $bootstrapper) {
        $this->assertSingletonBinding($app, $bootstrapper, $bootstrapper);
    }
});

it('keeps Database register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    $this->assertSingletonBinding($app, SchemaCatalogueSerializer::class, SchemaCatalogueSerializer::class);
    $this->assertSingletonBinding($app, TemplateIntegrityLock::class, TemplateIntegrityLock::class);
    $this->assertSingletonBinding(
        $app,
        DatabaseTemplateIntegrityVerifier::class,
        DatabaseTemplateIntegrityVerifier::class,
    );
    $this->assertSingletonBinding(
        $app,
        TemplateIntegrityVerifier::class,
        DatabaseTemplateIntegrityVerifier::class,
    );
});

it('keeps Provisioning register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    $this->assertSingletonBinding(
        $app,
        StanclTenantDatabaseProvisioner::class,
        StanclTenantDatabaseProvisioner::class,
    );
    $this->assertSingletonBinding($app, PendingPool::class, PendingPool::class);
    $this->assertSingletonBinding(
        $app,
        StanclTenantDatabaseLifecycle::class,
        StanclTenantDatabaseLifecycle::class,
    );
    $this->assertSingletonBinding(
        $app,
        TenantDatabaseLifecycle::class,
        StanclTenantDatabaseLifecycle::class,
    );
    $this->assertScopedBinding($app, AccountLifecycle::class, AccountLifecycle::class);
    $this->assertSingletonBinding(
        $app,
        MigrationWaveOrchestrator::class,
        MigrationWaveOrchestrator::class,
    );
});

it('keeps Identity register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    $this->assertSingletonBinding($app, DatabaseIdentitySyncer::class, DatabaseIdentitySyncer::class);
    $this->assertSingletonBinding($app, IdentitySyncer::class, DatabaseIdentitySyncer::class);
});

it('keeps Workspace register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    $this->assertSingletonBinding($app, WorkspaceResolver::class, DatabaseWorkspaceResolver::class);
    $this->assertTransientBinding(
        $app,
        PrincipalWorkspaceResolver::class,
        DatabaseWorkspaceResolver::class,
    );
});

it('keeps DomainTrust register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    $this->assertScopedBinding($app, DomainTrustStateWriter::class, DomainTrustStateWriter::class);
    expect($app->getBindings())->toHaveKey(DomainTrustCoordinator::class);
    expect($app->isShared(DomainTrustCoordinator::class))->toBeTrue();
});

it('omits Authentication bindings when oidc is disabled', function (): void {
    $app = $this->app;
    expect($app->getBindings())->not->toHaveKey(OidcDiscovery::class)
        ->and($app->getBindings())->not->toHaveKey(JwksKeySet::class)
        ->and($app->getBindings())->not->toHaveKey(IdTokenVerifier::class)
        ->and($app->getBindings())->not->toHaveKey(OidcClient::class)
        ->and($app->getBindings())->not->toHaveKey(LogoutController::class)
        ->and($app->getBindings())->not->toHaveKey(OidcLoginController::class);
    expect($app->make(ImpersonationService::class))->toBeInstanceOf(NullImpersonationService::class);
});

it('binds Authentication when oidc is enabled', function (): void {
    TestCase::$oidcEnabled = true;
    $this->refreshApplication();

    try {
        $app = $this->app;
        $this->assertSingletonBinding($app, OidcDiscovery::class, OidcDiscovery::class);
        $this->assertSingletonBinding($app, JwksKeySet::class, JwksKeySet::class);
        $this->assertSingletonBinding($app, IdTokenVerifier::class, IdTokenVerifier::class);
        $this->assertSingletonBinding($app, OidcClient::class, OidcClient::class);
        $this->assertSingletonBinding($app, LogoutController::class, LogoutController::class);
        $this->assertSingletonBinding($app, OidcLoginController::class, OidcLoginController::class);
        $this->assertScopedBinding(
            $app,
            ImpersonationService::class,
            DatabaseImpersonationService::class,
        );
    } finally {
        TestCase::$oidcEnabled = false;
    }
});

it('omits Wakeup bindings when driver is local_sweep', function (): void {
    $app = $this->app;
    expect($app->getBindings())->not->toHaveKey(RootRegistryWakeupHint::class)
        ->and($app->getBindings())->not->toHaveKey(RootWakeupReconciler::class);
});

it('binds Wakeup when driver is root_registry', function (): void {
    putenv('COREX_TENANCY_WAKEUP_DRIVER=root_registry');
    $this->refreshApplication();

    try {
        $app = $this->app;
        $this->assertSingletonBinding(
            $app,
            RootRegistryWakeupHint::class,
            RootRegistryWakeupHint::class,
        );
        expect($app->getBindings())->toHaveKey(RootWakeupReconciler::class);
        expect($app->isShared(RootWakeupReconciler::class))->toBeTrue();
        expect(iterator_to_array($app->tagged('corex.wakeup.hints')))->not->toBeEmpty();
    } finally {
        putenv('COREX_TENANCY_WAKEUP_DRIVER=local_sweep');
    }
});

it('registers composition modules through PackageRegistrar', function (): void {
    $registrars = [
        new RuntimeRegistrar,
        new DatabaseRegistrar,
        new ProvisioningRegistrar,
        new IdentityRegistrar,
        new WorkspaceRegistrar,
        new DomainTrustRegistrar,
        new AuthenticationRegistrar,
        new WakeupRegistrar,
    ];

    foreach ($registrars as $registrar) {
        expect($registrar)->toBeInstanceOf(PackageRegistrar::class);
        $registrar->boot($this->app);
    }
});
