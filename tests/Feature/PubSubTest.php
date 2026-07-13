<?php

declare(strict_types=1);

use CoreX\Contracts\PubSub;
use CoreX\Enums\PubSubDriver;
use CoreX\PubSub\NullPubSub;
use CoreX\PubSub\PubSubManager;
use CoreX\PubSub\RedisPubSub;

function resolvedPubSubDriver(): PubSub
{
    /** @var PubSubManager $manager */
    $manager = app(PubSub::class);

    $method = new ReflectionMethod(PubSubManager::class, 'driver');
    $method->setAccessible(true);

    /** @var PubSub */
    return $method->invoke($manager);
}

it('delivers a publish()ed payload to a subscribe()d handler on the null driver', function (): void {
    $pubsub = new NullPubSub;
    $received = [];

    $pubsub->subscribe('module.enabled', function (array $payload) use (&$received): void {
        $received[] = $payload;
    });

    $pubsub->publish('module.enabled', ['module' => 'crm']);
    $pubsub->publish('module.enabled', ['module' => 'tasks']);
    $pubsub->publish('other.channel', ['module' => 'ignored']);

    expect($received)->toBe([
        ['module' => 'crm'],
        ['module' => 'tasks'],
    ]);
});

it('AC-25(b): PubSubManager resolves the null driver from corex.pubsub.driver', function (): void {
    config(['corex.pubsub.driver' => PubSubDriver::Null]);
    expect(app(PubSub::class))->toBeInstanceOf(PubSubManager::class)
        ->and(resolvedPubSubDriver())->toBeInstanceOf(NullPubSub::class);
});

it('AC-25(b): PubSubManager resolves RedisPubSub for the redis driver (class resolution only, no live Redis call)', function (): void {
    config(['corex.pubsub.driver' => PubSubDriver::Redis]);
    expect(resolvedPubSubDriver())->toBeInstanceOf(RedisPubSub::class);
});

it('grep: no LISTEN/NOTIFY SQL literal anywhere in the PubSub driver sources (B-10 §7.3 п.4, R-15)', function (): void {
    $files = glob(__DIR__.'/../../packages/core/src/PubSub/*.php');

    expect($files)->not->toBeEmpty();

    // Matches an actual SQL string literal (`"LISTEN foo"`, `'NOTIFY foo'`),
    // not the words as they appear in prose/docblocks explaining the ban.
    foreach ($files as $file) {
        $source = (string) file_get_contents($file);
        expect($source)->not->toMatch('/[\'"]\s*(LISTEN|NOTIFY)\s+/i');
    }
});
