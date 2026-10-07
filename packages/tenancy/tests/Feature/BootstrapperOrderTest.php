<?php

declare(strict_types=1);

use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\CachePrefixBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\ConnectionBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\SessionConnectionBootstrapper;

it('сохраняет порядок трёх штатных bootstrapper-ов байтово', function (): void {
    $expected = [
        ConnectionBootstrapper::class,
        CachePrefixBootstrapper::class,
        SessionConnectionBootstrapper::class,
    ];

    expect(config('corex-tenancy.bootstrappers'))->toBe($expected)
        ->and(resolve(TenancyOptions::class)->bootstrappers)->toBe($expected);
});

it('uses only the package configuration namespace', function (): void {
    expect(config('tenancy'))->toBeNull();
});

it('штатные bootstrapper-ы — singleton в контейнере, а порядок в менеджере совпадает с options', function (): void {
    foreach (resolve(TenancyOptions::class)->bootstrappers as $class) {
        expect(resolve($class))->toBe(resolve($class));
    }
});
