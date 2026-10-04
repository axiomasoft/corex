<?php

declare(strict_types=1);

it('publishes control plane migrations only to the documented root directory', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'corex-tenancy-migrations', '--force' => true])->assertSuccessful();
    expect(glob(database_path('migrations/*.php')))->toBe([])
        ->and(glob(database_path('migrations/root/*.php')))->not->toBeEmpty();
});

it('publishes the complete configuration under the package key', function (): void {
    $this->artisan('vendor:publish', ['--tag' => 'corex-tenancy-config', '--force' => true])->assertSuccessful();
    expect(is_file(config_path('corex-tenancy.php')))->toBeTrue()
        ->and(is_file(config_path('tenancy.php')))->toBeFalse()
        ->and(config('corex-tenancy.wakeup.driver'))->toBe('local_sweep')
        ->and(config('tenancy'))->toBeNull();
});
