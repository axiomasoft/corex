<?php

declare(strict_types=1);

use CoreX\Tenancy\Laravel\Bootstrappers\WorkspaceContextBootstrapper;
use Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\DatabaseSessionBootstrapper;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\Integrations\ScoutPrefixBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;

it('сохраняет порядок семи bootstrapper-ов байтово', function (): void {
    $expected = [
        DatabaseTenancyBootstrapper::class,
        WorkspaceContextBootstrapper::class,
        CacheTenancyBootstrapper::class,
        DatabaseSessionBootstrapper::class,
        QueueTenancyBootstrapper::class,
        FilesystemTenancyBootstrapper::class,
        ScoutPrefixBootstrapper::class,
    ];

    $order = config('tenancy.bootstrapper_order');
    $projected = config('tenancy.bootstrappers');

    expect($order)->toBe($expected)
        ->and($projected)->toBe($expected);

    foreach ($expected as $index => $class) {
        expect($order[$index])->toBe($class)
            ->and($projected[$index])->toBe($class);
    }
});
