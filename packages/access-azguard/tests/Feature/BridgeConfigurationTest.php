<?php

declare(strict_types=1);

use AzGuard\Contracts\ContextGuard;
use AzGuard\Contracts\PermissionLayer;
use AzGuard\Models\Role;
use CoreX\AccessAzguard\AccessAzguardRegistrar;
use CoreX\AccessAzguard\AccessAzguardServiceProvider;
use CoreX\AccessAzguard\Internal\AccessAzguardOptions;
use CoreX\AccessAzguard\Internal\AzGuardRoleModel;
use CoreX\AccessAzguard\Internal\TenantContextGuard;
use CoreX\AccessAzguard\Internal\WorkspacePermissionLayer;
use CoreX\Config\Exceptions\InvalidConfiguration;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;

it('resolves bridge options as a shared immutable snapshot', function (): void {
    $options = resolve(AccessAzguardOptions::class);
    config()->set('corex-access-azguard.azguard_pins.table_names.roles', 'changed_roles');

    expect(resolve(AccessAzguardOptions::class))->toBe($options)
        ->and($options->rolesTable)->toBe('az_guard_roles');
    expect(function () use ($options): void {
        $options->rolesTable = 'mutable_roles';
    })->toThrow(Error::class);
});

it('keeps the shared bridge options unresolved during registration', function (): void {
    $application = new Application;
    $application->instance('config', new Repository);
    new AccessAzguardServiceProvider($application)->register();

    expect($application->resolved(AccessAzguardOptions::class))->toBeFalse();
    $application->make('config')->set('corex-access-azguard.bind_permission_layer', false);
    $options = $application->make(AccessAzguardOptions::class);
    expect($options->bindPermissionLayer)->toBeFalse()
        ->and($application->make(AccessAzguardOptions::class))->toBe($options);
});

it('applies bridge pins through the public registrar over conflicting AzGuard config', function (): void {
    config()->set('az-guard.table_names.roles', 'external_roles');
    config()->set('az-guard.column_names.morph_type', 'int');
    config()->set('az-guard.models.role', Role::class);
    config()->set('corex-access-azguard.azguard_pins.table_names.roles', 'bridge_roles');

    (new AccessAzguardRegistrar)->register($this->app);

    expect(config('az-guard.table_names.roles'))->toBe('bridge_roles')
        ->and(config('az-guard.column_names.morph_type'))->toBe('uuid')
        ->and(config('az-guard.models.role'))->toBe(AzGuardRoleModel::class)
        ->and(resolve(ContextGuard::class))->toBeInstanceOf(TenantContextGuard::class)
        ->and(resolve(PermissionLayer::class))->toBeInstanceOf(WorkspacePermissionLayer::class);
});

it('retains an existing permission layer binding when bridge binding is disabled', function (): void {
    config()->set('corex-access-azguard.bind_permission_layer', false);
    $layer = Mockery::mock(PermissionLayer::class);
    app()->instance(PermissionLayer::class, $layer);

    (new AccessAzguardRegistrar)->register($this->app);

    expect(resolve(PermissionLayer::class))->toBe($layer);
});

it('has usable bridge options defaults on an empty configuration', function (): void {
    $options = AccessAzguardOptions::fromArray([]);

    expect($options->bindPermissionLayer)->toBeTrue()
        ->and($options->rolesTable)->toBe('az_guard_roles')
        ->and($options->morphType)->toBe('uuid')
        ->and($options->roleModel)->toBe(AzGuardRoleModel::class);
});

it('accepts the documented AzGuard pin override shapes', function (): void {
    $options = AccessAzguardOptions::fromArray([
        'bind_permission_layer' => false,
        'azguard_pins' => [
            'table_names' => ['roles' => 'custom_roles'],
            'column_names' => ['morph_type' => 'ulid'],
            'models' => ['role' => Role::class],
        ],
    ]);

    expect($options->bindPermissionLayer)->toBeFalse()
        ->and($options->rolesTable)->toBe('custom_roles')
        ->and($options->morphType)->toBe('ulid')
        ->and($options->roleModel)->toBe(Role::class)
        ->and(AccessAzguardOptions::fromArray(['azguard_pins' => ['models' => ['role' => null]]])->roleModel)->toBeNull();
});

it('rejects invalid bridge configuration with the offending path', function (array $config, string $path): void {
    try {
        AccessAzguardOptions::fromArray($config);
        $this->fail('Invalid bridge configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toHaveCount(1)
            ->and($exception->violations[0])->toStartWith($path.':');
    }
})->with([
    'binding flag' => [['bind_permission_layer' => 'true'], 'bind_permission_layer'],
    'pin group' => [['azguard_pins' => 'invalid'], 'azguard_pins'],
    'tables group' => [['azguard_pins' => ['table_names' => false]], 'azguard_pins.table_names'],
    'roles table' => [['azguard_pins' => ['table_names' => ['roles' => '']]], 'azguard_pins.table_names.roles'],
    'morph type' => [['azguard_pins' => ['column_names' => ['morph_type' => 'bad']]], 'azguard_pins.column_names.morph_type'],
    'role model' => [['azguard_pins' => ['models' => ['role' => stdClass::class]]], 'azguard_pins.models.role'],
]);

it('validates the lazy bridge options before booting runtime integrations', function (): void {
    $application = new Application;
    $application->instance('config', new Repository);
    $provider = new AccessAzguardServiceProvider($application);
    $provider->register();
    $application->make('config')->set('corex-access-azguard.bind_permission_layer', 'invalid');

    expect(fn () => $provider->boot())->toThrow(InvalidConfiguration::class);
});
