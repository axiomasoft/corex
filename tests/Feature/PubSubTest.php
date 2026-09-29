<?php

declare(strict_types=1);

use CoreX\Messaging\Contracts\PubSub;
use CoreX\Messaging\Contracts\WorkerRuntime;
use CoreX\Messaging\Enums\PubSubDriver;
use CoreX\Messaging\Internal\DatabasePollPubSub;
use CoreX\Messaging\Internal\NullPubSub;
use CoreX\Messaging\Internal\RedisPubSub;
use CoreX\Messaging\Internal\SubscribeGuard;
use Illuminate\Support\Facades\Redis;

/**
 * Records every publish() call it receives — swapped into the container in
 * place of the real driver class so driver-selection tests observe behavior
 * through the public PubSub contract (PubSubManager::publish()) instead of
 * reflecting into PubSubManager's private driver() method (A32/A34).
 */
final class RecordingPubSub implements PubSub
{
    /** @var list<array{channel: string, payload: array<string, mixed>}> */
    public array $published = [];

    public function publish(string $channel, array $payload): void
    {
        $this->published[] = ['channel' => $channel, 'payload' => $payload];
    }

    public function subscribe(string $channel, Closure $handler): void
    {
        // Not exercised by the driver-selection tests below.
    }
}

/** Test-only WorkerRuntime double — the seam Implementation Rules requires so a negative guard test can fake "running under Octane" without installing laravel/octane (A78). */
final class FakeWorkerRuntime implements WorkerRuntime
{
    public function __construct(
        private readonly bool $octane,
        private readonly bool $console,
    ) {}

    public function isOctane(): bool
    {
        return $this->octane;
    }

    public function runningInConsole(): bool
    {
        return $this->console;
    }
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

it('(е) publish → subscribe delivers through app(PubSub::class) on the null driver', function (): void {
    config(['corex.pubsub.driver' => PubSubDriver::Null]);
    $received = [];

    app(PubSub::class)->subscribe('module.enabled', function (array $payload) use (&$received): void {
        $received[] = $payload;
    });
    app(PubSub::class)->publish('module.enabled', ['module' => 'crm']);

    expect($received)->toBe([['module' => 'crm']]);
});

it('AC-25(b)/(в) PubSubManager delegates publish() to the null driver selected by corex.pubsub.driver — behavioral, no reflection', function (): void {
    config(['corex.pubsub.driver' => PubSubDriver::Null]);
    $spy = new RecordingPubSub;
    app()->instance(NullPubSub::class, $spy);

    app(PubSub::class)->publish('module.enabled', ['module' => 'crm']);

    expect($spy->published)->toBe([['channel' => 'module.enabled', 'payload' => ['module' => 'crm']]]);
});

it('AC-25(b)/(в) PubSubManager delegates publish() to the redis driver selected by corex.pubsub.driver — behavioral, no live Redis call', function (): void {
    config(['corex.pubsub.driver' => PubSubDriver::Redis]);
    $spy = new RecordingPubSub;
    app()->instance(RedisPubSub::class, $spy);

    app(PubSub::class)->publish('module.enabled', ['module' => 'crm']);

    expect($spy->published)->toBe([['channel' => 'module.enabled', 'payload' => ['module' => 'crm']]]);
});

it('AC-25(b)/(в) PubSubManager delegates publish() to the database driver selected by corex.pubsub.driver — behavioral, no live DB call', function (): void {
    config(['corex.pubsub.driver' => PubSubDriver::Database]);
    $spy = new RecordingPubSub;
    app()->instance(DatabasePollPubSub::class, $spy);

    app(PubSub::class)->publish('module.enabled', ['module' => 'crm']);

    expect($spy->published)->toBe([['channel' => 'module.enabled', 'payload' => ['module' => 'crm']]]);
});

it('(a) P1.24/A29: subscribe() refuses a faked Octane runtime without explicit opt-in (negative guard test)', function (): void {
    Redis::shouldReceive('connection')->never();
    $pubsub = new RedisPubSub(runtime: new FakeWorkerRuntime(octane: true, console: true));

    expect(fn () => $pubsub->subscribe('module.enabled', function (): void {}))
        ->toThrow(LogicException::class, 'Octane runtime');
});

it('P1.24: subscribe() refuses a non-console, non-Octane runtime (HTTP request cycle)', function (): void {
    $pubsub = new RedisPubSub(runtime: new FakeWorkerRuntime(octane: false, console: false));

    expect(fn () => $pubsub->subscribe('module.enabled', function (): void {}))
        ->toThrow(LogicException::class, 'never inside an HTTP request cycle');
});

it('P1.24: SubscribeGuard allows an Octane runtime that explicitly opted in', function (): void {
    SubscribeGuard::assert(new FakeWorkerRuntime(octane: true, console: true), octaneWorkerOptIn: true, driverClass: RedisPubSub::class);
})->throwsNoExceptions();
