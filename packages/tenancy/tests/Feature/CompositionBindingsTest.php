<?php

declare(strict_types=1);

use CoreX\Context\Contracts\IdentityPasswordRehasher;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Contracts\TenantDatabaseLifecycle;
use CoreX\Context\Internal\NullImpersonationService;
use CoreX\Context\Laravel\WorkspaceScopeProvider;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Storage\Contracts\TenantStorage;
use CoreX\Tenancy\Account\Internal\DatabaseTenantProvisioner;
use CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycle;
use CoreX\Tenancy\Account\Internal\PendingPool;
use CoreX\Tenancy\Account\Laravel\MigrationWaveOrchestrator;
use CoreX\Tenancy\Authentication\Internal\Http\Controllers\LogoutController;
use CoreX\Tenancy\Authentication\Internal\Http\Controllers\OidcLoginController;
use CoreX\Tenancy\Authentication\Internal\Oidc\IdTokenVerifier;
use CoreX\Tenancy\Authentication\Internal\Oidc\JwksKeySet;
use CoreX\Tenancy\Authentication\Internal\Oidc\OidcClient;
use CoreX\Tenancy\Authentication\Internal\Oidc\OidcDiscovery;
use CoreX\Tenancy\Contracts\PrincipalWorkspaceResolver;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\DomainTrust\Internal\DomainTrustCoordinator;
use CoreX\Tenancy\DomainTrust\Internal\DomainTrustStateWriter;
use CoreX\Tenancy\Identity\Contracts\IdentitySyncer;
use CoreX\Tenancy\Identity\Internal\DatabaseIdentitySyncer;
use CoreX\Tenancy\Impersonation\Internal\DatabaseImpersonationService;
use CoreX\Tenancy\Integration\Internal\Wakeup\RootRegistryWakeupHint;
use CoreX\Tenancy\Integration\Internal\Wakeup\RootWakeupReconciler;
use CoreX\Tenancy\Laravel\AuthenticationRegistrar;
use CoreX\Tenancy\Laravel\DatabaseRegistrar;
use CoreX\Tenancy\Laravel\DomainTrustRegistrar;
use CoreX\Tenancy\Laravel\IdentityRegistrar;
use CoreX\Tenancy\Laravel\ProvisioningRegistrar;
use CoreX\Tenancy\Laravel\RuntimeRegistrar;
use CoreX\Tenancy\Laravel\Scopes\WorkspaceScope;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Laravel\WakeupRegistrar;
use CoreX\Tenancy\Laravel\WorkspaceRegistrar;
use CoreX\Tenancy\Provisioning\Internal\Database\SchemaCatalogueSerializer;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityLock;
use CoreX\Tenancy\Provisioning\Internal\DatabaseTenantLifecycle;
use CoreX\Tenancy\Runtime\Internal\ConnectionGuardedTenantContextRestorer;
use CoreX\Tenancy\Runtime\Internal\DatabaseTenancyManager;
use CoreX\Tenancy\Runtime\Internal\QueueTenantContext;
use CoreX\Tenancy\Runtime\Internal\Storage\ContextTenantStorage;
use CoreX\Tenancy\Runtime\Internal\TenancyManagerContextResolver;
use CoreX\Tenancy\Tests\Concerns\AssertsContainerBindings;
use CoreX\Tenancy\Tests\TestCase;
use CoreX\Tenancy\Workspace\Contracts\WorkspaceResolver;
use CoreX\Tenancy\Workspace\Internal\DatabaseWorkspaceResolver;
use CoreX\Tenancy\Workspace\Internal\TenancyWorkspaceScopeProvider;

uses(AssertsContainerBindings::class);
uses()->group('pg');

beforeEach(function (): void {
    if (! $this->app->bound(ImpersonationService::class)) {
        $this->app->singleton(ImpersonationService::class, NullImpersonationService::class);
    }
});

it('keeps Runtime register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    $this->assertSingletonBinding($app, DatabaseTenancyManager::class, DatabaseTenancyManager::class);
    $this->assertSingletonBinding($app, TenancyManager::class, DatabaseTenancyManager::class);
    $this->assertSingletonBinding($app, QueueTenantContext::class, QueueTenantContext::class);
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

    foreach ($app->make(TenancyOptions::class)->bootstrappers as $bootstrapper) {
        $this->assertSingletonBinding($app, $bootstrapper, $bootstrapper);
    }
});

it('binds the identity password rehash port to the tenancy table owner', function (): void {
    $this->assertSingletonBinding($this->app, IdentityPasswordRehasher::class, DatabaseIdentitySyncer::class);
});

it('keeps Database register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    $this->assertSingletonBinding($app, SchemaCatalogueSerializer::class, SchemaCatalogueSerializer::class);
    $this->assertSingletonBinding($app, TemplateIntegrityLock::class, TemplateIntegrityLock::class);
    $this->assertSingletonBinding(
        $app,
        CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier::class,
        CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier::class,
    );
    $this->assertSingletonBinding(
        $app,
        TemplateIntegrityVerifier::class,
        CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier::class,
    );
});

it('keeps Provisioning register bindings on the measured key, concrete and lifetime', function (): void {
    $app = $this->app;
    $this->assertSingletonBinding(
        $app,
        DatabaseTenantProvisioner::class,
        DatabaseTenantProvisioner::class,
    );
    $this->assertSingletonBinding($app, PendingPool::class, PendingPool::class);
    $this->assertSingletonBinding(
        $app,
        DatabaseTenantLifecycle::class,
        DatabaseTenantLifecycle::class,
    );
    $this->assertSingletonBinding(
        $app,
        TenantDatabaseLifecycle::class,
        DatabaseTenantLifecycle::class,
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
    $this->assertSingletonBinding($app, WorkspaceScopeProvider::class, TenancyWorkspaceScopeProvider::class);
    expect($app->make(WorkspaceScopeProvider::class)->scopeClass())->toBe(WorkspaceScope::class);
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
        // Scoped: the controller stores ImpersonationService, which is itself scoped.
        // A singleton would keep the first impersonation service for the process.
        $this->assertScopedBinding($app, OidcLoginController::class, OidcLoginController::class);
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
