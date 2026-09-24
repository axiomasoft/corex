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
        expect($first)->toBe($second);
        $app->forgetScopedInstances();
        expect($app->make($abstract))->toBe($first);
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
        expect($app->make($abstract))->toBe($first);
        $app->forgetScopedInstances();
        $next = $app->make($abstract);
        expect($next)->toBeInstanceOf($expectedConcrete);
        expect($next)->not->toBe($first);
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
        expect($first)->not->toBe($second);
    }
}
