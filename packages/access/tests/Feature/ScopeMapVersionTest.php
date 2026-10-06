<?php

declare(strict_types=1);

use CoreX\Access\Laravel\AuthOptions;
use CoreX\Access\Matrix\Internal\ScopeMapVersion;
use CoreX\Context\Internal\SingleAccountResolver;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\Factory;
use Illuminate\Contracts\Cache\Repository;

it('reads valid cache versions without losing their numeric value', function (int|string $stored, int $expected): void {
    $context = new SingleAccountResolver;
    $options = app(AuthOptions::class);
    $store = app(Factory::class)->store($options->cacheStore);
    $store->put('corex-access.scope-map.'.$context->current()->account->id.'.role', $stored);

    expect((new ScopeMapVersion($context, app(Factory::class), $options))->current('role'))->toBe($expected);
})->with([
    'integer' => [3, 3],
    'numeric string' => ['3', 3],
    'zero' => [0, 0],
]);

it('rejects malformed cached versions rather than using a stale zero version', function (mixed $stored): void {
    $context = new SingleAccountResolver;
    $options = app(AuthOptions::class);
    $store = app(Factory::class)->store($options->cacheStore);
    $store->put('corex-access.scope-map.'.$context->current()->account->id.'.role', $stored);
    $versions = new ScopeMapVersion($context, app(Factory::class), $options);

    expect(fn () => $versions->current('role'))->toThrow(UnexpectedValueException::class);
})->with([
    'boolean' => [false],
    'fraction' => [1.5],
    'array' => [['version' => 3]],
    'text' => ['invalid'],
    'negative' => [-1],
    'overflow' => ['999999999999999999999999999999'],
]);

it('does not report a successful invalidation when the backend fails to increment', function (): void {
    $store = Mockery::mock(Repository::class);
    $store->shouldReceive('getStore')->andReturn(new ArrayStore);
    $store->shouldReceive('add')->once()->andReturn(true);
    $store->shouldReceive('increment')->once()->andReturn(false);
    $factory = Mockery::mock(Factory::class);
    $factory->shouldReceive('store')->andReturn($store);
    $versions = new ScopeMapVersion(new SingleAccountResolver, $factory, app(AuthOptions::class));

    expect(fn () => $versions->bump('role'))->toThrow(UnexpectedValueException::class);
});
