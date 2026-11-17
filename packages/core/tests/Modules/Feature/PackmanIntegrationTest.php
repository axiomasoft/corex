<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Foundation\Internal\PackmanFeatureFlags;
use Packman\Context\Contracts\ContextResolverInterface;
use Packman\Features\Contracts\FeatureFlagsInterface;
use Packman\Schema\Contracts\Migration\SchemaSets;

it('adapts the current account and domain feature store to Packman', function (): void {
    $core = resolve(TenantContextResolver::class)->current();
    $external = resolve(ContextResolverInterface::class)->current();

    expect($external)->toEqual($core)
        ->and($external->id())->toBe($core->account->id)
        ->and(resolve(FeatureFlagsInterface::class))->toBeInstanceOf(PackmanFeatureFlags::class);
});

it('registers one upstream control-plane schema and the independent CoreX schema', function (): void {
    $sets = resolve(SchemaSets::class);
    $modules = $sets->get('axiomasoft/packman-modules:tenant');
    $core = $sets->get('axiomasoft/corex:tenant');

    expect($modules->package)->toBe('axiomasoft/packman-modules')
        ->and($core->package)->toBe('axiomasoft/corex')
        ->and(glob($core->path.'/*create_mod_*.php'))->toBe([])
        ->and(glob($modules->path.'/*create_mod_*.php'))->toHaveCount(3);
});
