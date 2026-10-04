<?php

declare(strict_types=1);

use CoreX\Tenancy\Laravel\Support\TenancyOptions;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\CachePrefixBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\ConnectionBootstrapper;
use CoreX\Tenancy\Runtime\Internal\Bootstrappers\SessionConnectionBootstrapper;
use Stancl\Tenancy\TenancyServiceProvider;

it('сохраняет порядок трёх штатных bootstrapper-ов байтово', function (): void {
    $expected = [
        ConnectionBootstrapper::class,
        CachePrefixBootstrapper::class,
        SessionConnectionBootstrapper::class,
    ];

    expect(config('tenancy.bootstrappers'))->toBe($expected)
        ->and(app(TenancyOptions::class)->bootstrappers)->toBe($expected);
});

it('не оставляет runtime-хуков stancl: нет ключа bootstrapper_order, нет идентификации и мидлвари stancl', function (): void {
    expect(config('tenancy.bootstrapper_order'))->toBeNull()
        ->and(config('tenancy.identification.resolvers'))->toBeNull()
        ->and(config('tenancy.identification.middleware'))->toBeNull()
        ->and(app()->getProviders(TenancyServiceProvider::class))->toBe([]);
});

it('штатные bootstrapper-ы — singleton в контейнере, а порядок в менеджере совпадает с options', function (): void {
    foreach (app(TenancyOptions::class)->bootstrappers as $class) {
        expect(app($class))->toBe(app($class));
    }
});
