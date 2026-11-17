<?php

declare(strict_types=1);

use CoreX\CoreServiceProvider;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Tests\TestCase;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Settings\Data\SettingsScope;
use CoreX\Time\Testing\FrozenClock;
use Psr\Clock\ClockInterface;

uses(TestCase::class);

final class DigestClockSettingsRepository implements SettingsRepository
{
    public function get(string $namespace, string $key, ?SettingsScope $scope = null, ?JsonDocument $default = null): JsonDocument
    {
        return JsonDocuments::capture($default);
    }

    public function set(string $namespace, string $key, JsonDocument $value, ?SettingsScope $scope = null): void {}

    public function forget(string $namespace, string $key, ?SettingsScope $scope = null): void {}

    public function all(string $namespace, ?SettingsScope $scope = null): array
    {
        return [];
    }
}

function bindDigestClockSettings(): void
{
    app()->instance(SettingsRepository::class, new DigestClockSettingsRepository);
    app()->forgetScopedInstances();
}

it('resolves wakeup from the isolated notifications composition without CoreServiceProvider', function (): void {
    expect(app()->getLoadedProviders())->not->toHaveKey(CoreServiceProvider::class);

    $clock = resolve(ClockInterface::class);

    expect($clock)->toBeInstanceOf(ClockInterface::class)
        ->and($clock->now())->toBeInstanceOf(DateTimeImmutable::class)
        ->and($clock->now()->getTimezone()->getName())->toBe('UTC');

    bindDigestClockSettings();

    expect(resolve(WakeupScheduler::class))->toBeInstanceOf(WakeupScheduler::class)
        ->and(resolve(wakeup_bridge_sweeper()))->toBeInstanceOf(wakeup_bridge_sweeper())
        ->and(resolve(ClockInterface::class))->toBe($clock);
});

it('preserves a FrozenClock already bound beside the notifications wakeup composition', function (): void {
    $clock = new FrozenClock(new DateTimeImmutable('2026-03-04T05:06:07Z'));
    app()->instance(ClockInterface::class, $clock);
    app()->forgetScopedInstances();
    wakeup_bridge_register_bindings();

    expect(resolve(ClockInterface::class))->toBe($clock);

    bindDigestClockSettings();

    resolve(WakeupScheduler::class);
    resolve(wakeup_bridge_sweeper());

    expect(resolve(ClockInterface::class))->toBe($clock)
        ->and($clock->now()->format(DateTimeImmutable::ATOM))->toBe('2026-03-04T05:06:07+00:00');
});
