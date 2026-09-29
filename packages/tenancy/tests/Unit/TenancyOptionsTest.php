<?php

declare(strict_types=1);

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Tenancy\Account\Models\Account;
use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Provisioning\Database\TemplateClonePostgreSQLManager;
use CoreX\Tenancy\Provisioning\Jobs\CreateDatabaseFromTemplate;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\UniqueIdentifierGenerators\UUIDv7Generator;

/**
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function validTenancyConfigArrays(): array
{
    return [
        [
            'central_connection' => 'pgsql',
            'tenant_model' => Account::class,
            'id_generator' => UUIDv7Generator::class,
            'tenant_database_manager' => TemplateClonePostgreSQLManager::class,
            'bootstrapper_order' => [DatabaseTenancyBootstrapper::class],
            'provisioning' => ['pipeline' => [CreateDatabaseFromTemplate::class]],
            'cache_prefix' => 'tenant_%tenant%_',
            'storage' => [
                'durable_driver' => 'local',
                'durable_root' => '/tmp/durable',
                'ephemeral_root' => '/tmp/ephemeral',
            ],
            'host_identification' => [
                'enabled' => false,
                'cache' => true,
                'cache_ttl' => 300,
                'cache_store' => null,
            ],
            'domain_trust' => [
                'enabled' => false,
                'challenge_ttl_seconds' => 300,
            ],
            'status_gate' => [
                'payment_route' => null,
                'export_route' => null,
                'read_only_methods' => ['GET', 'HEAD', 'OPTIONS'],
            ],
            'oidc' => [
                'enabled' => false,
                'guard' => 'web',
                'allowed_algs' => ['RS256'],
                'leeway_seconds' => 30,
                'discovery' => ['cache_ttl' => 3600],
                'jwks' => [
                    'cache_ttl' => 3600,
                    'refetch_cooldown_seconds' => 60,
                ],
                'impersonation' => [
                    'enabled' => false,
                    'require_approved_by' => false,
                ],
                'max_session_lifetime_minutes' => 720,
            ],
            'workspace' => ['path_segment' => 'w'],
            'trial_days' => 14,
            'grace_days' => 30,
            'platform_domain' => 'example.test',
            'template_integrity' => [
                'trusted_keys' => [],
                'manifests' => [],
            ],
        ],
        [
            'wakeup' => [
                'driver' => 'local_sweep',
                'root_connection' => 'pgsql',
                'lease_seconds' => 60,
            ],
        ],
    ];
}

it('builds options from a valid tenancy snapshot', function (): void {
    [$tenancy, $corexTenancy] = validTenancyConfigArrays();

    $options = TenancyOptions::fromArray($tenancy, $corexTenancy);

    expect($options->centralConnection)->toBe('pgsql')
        ->and($options->wakeupDriver)->toBe('local_sweep')
        ->and($options->statusGate->readOnlyMethods)->toBe(['GET', 'HEAD', 'OPTIONS']);
});

it('rejects oidc enabled without client secret', function (): void {
    [$tenancy, $corexTenancy] = validTenancyConfigArrays();
    $tenancy['oidc'] = [
        ...$tenancy['oidc'],
        'enabled' => true,
        'issuer' => 'https://issuer.example',
        'client_id' => 'client',
        'redirect_uri' => 'https://app.example/callback',
        'client_secret' => null,
    ];

    try {
        TenancyOptions::fromArray($tenancy, $corexTenancy);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toContain(
            'oidc.client_secret: must be present when oidc.enabled is true',
        );
    }
});

it('rejects root_registry wakeup without root connection', function (): void {
    [$tenancy, $corexTenancy] = validTenancyConfigArrays();
    $corexTenancy['wakeup'] = [
        ...$corexTenancy['wakeup'],
        'driver' => 'root_registry',
        'root_connection' => '',
    ];

    try {
        TenancyOptions::fromArray($tenancy, $corexTenancy);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toContain(
            'wakeup.root_connection: must be non-empty when wakeup.driver is root_registry',
        );
    }
});

it('accumulates oidc and wakeup violations in one exception', function (): void {
    [$tenancy, $corexTenancy] = validTenancyConfigArrays();
    $tenancy['oidc'] = [
        ...$tenancy['oidc'],
        'enabled' => true,
        'issuer' => 'https://issuer.example',
        'client_id' => 'client',
        'redirect_uri' => 'https://app.example/callback',
        'client_secret' => '',
    ];
    $corexTenancy['wakeup'] = [
        ...$corexTenancy['wakeup'],
        'driver' => 'root_registry',
        'root_connection' => '',
    ];

    try {
        TenancyOptions::fromArray($tenancy, $corexTenancy);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toHaveCount(2)
            ->and($exception->violations)->toContain(
                'oidc.client_secret: must be present when oidc.enabled is true',
                'wakeup.root_connection: must be non-empty when wakeup.driver is root_registry',
            );
    }
});
