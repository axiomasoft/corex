<?php

declare(strict_types=1);

use CoreX\Time\Laravel\LaravelClock;
use CoreX\Time\Laravel\SystemClock;
use CoreX\Time\Testing\FrozenClock;
use Psr\Clock\ClockInterface;

it('returns UTC from system clock', function (): void {
    $clock = new SystemClock;

    expect($clock->now()->getTimezone()->getName())->toBe('UTC');
});

it('advances frozen clock by the given interval', function (): void {
    $clock = new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00', timezone: new DateTimeZone('UTC')));

    expect($clock->now()->format('Y-m-d'))->toBe('2020-01-01');

    $clock->advance(new DateInterval('P1D'));

    expect($clock->now()->format('Y-m-d'))->toBe('2020-01-02');
});

it('reads time frozen via travelTo through the default clock binding', function (): void {
    $this->travelTo('2026-01-01 00:00:00');

    $clock = $this->app->make(ClockInterface::class);

    expect($clock)->toBeInstanceOf(LaravelClock::class)
        ->and($clock->now()->format('Y-m-d H:i:s'))->toBe('2026-01-01 00:00:00');
});
