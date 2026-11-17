<?php

declare(strict_types=1);
use Packman\Schema\Contracts\Migration\SchemaSets;
use Packman\Schema\Enums\SchemaScope;

it('registers control plane and tenant schema on distinct scopes', function (): void {
    $sets = resolve(SchemaSets::class);
    expect($sets->get('axiomasoft/corex-tenancy:root')->scope)->toBe(SchemaScope::Central)
        ->and($sets->get('axiomasoft/corex-tenancy:tenant')->scope)->toBe(SchemaScope::Tenant)
        ->and(glob(database_path('migrations/*.php')))->toBe([]);
});

it('publishes the complete configuration under the package key', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'corex-tenancy-config', '--force' => true])->assertSuccessful();
    expect(is_file(config_path('corex-tenancy.php')))->toBeTrue()
        ->and(is_file(config_path('tenancy.php')))->toBeFalse()
        ->and(config('corex-tenancy.wakeup.driver'))->toBe('local_sweep')
        ->and(config('tenancy'))->toBeNull();
});
