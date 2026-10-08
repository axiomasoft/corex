<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Concerns;

use Illuminate\Foundation\Application;

trait AssertsContainerBindings
{
    private function assertSingletonBinding(
        Application $app,
        string $abstract,
        string $expectedConcrete,
    ): void {
        expect($app->getBindings())->toHaveKey($abstract);
        expect($app->isShared($abstract))->toBeTrue();
        $first = $app->make($abstract);
        $second = $app->make($abstract);
        expect($first)->toBeInstanceOf($expectedConcrete);
        expect(spl_object_id($first))->toBe(spl_object_id($second));
        $app->forgetScopedInstances();
        expect(spl_object_id($app->make($abstract)))->toBe(spl_object_id($first));
    }

    private function assertScopedBinding(
        Application $app,
        string $abstract,
        string $expectedConcrete,
    ): void {
        expect($app->getBindings())->toHaveKey($abstract);
        expect($app->isShared($abstract))->toBeTrue();
        $first = $app->make($abstract);
        expect($first)->toBeInstanceOf($expectedConcrete);
        // Object ids, not objects: an identity diff would export the container and its environment.
        expect(spl_object_id($app->make($abstract)))->toBe(spl_object_id($first));
        $app->forgetScopedInstances();
        $next = $app->make($abstract);
        expect($next)->toBeInstanceOf($expectedConcrete);
        expect(spl_object_id($next))->not->toBe(spl_object_id($first));
    }

    private function assertTransientBinding(
        Application $app,
        string $abstract,
        string $expectedConcrete,
    ): void {
        expect($app->getBindings())->toHaveKey($abstract);
        expect($app->isShared($abstract))->toBeFalse();
        $first = $app->make($abstract);
        $second = $app->make($abstract);
        expect($first)->toBeInstanceOf($expectedConcrete);
        expect(spl_object_id($first))->not->toBe(spl_object_id($second));
    }
}
