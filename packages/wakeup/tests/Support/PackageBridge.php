<?php

declare(strict_types=1);

use CoreX\Wakeup\Contracts\DueWakeupSweeper;
use CoreX\Wakeup\Internal\WakeupOptions;
use CoreX\Wakeup\Testing\WakeupFixtures;
use CoreX\Wakeup\WakeupServiceProvider;

function wakeup_bridge_provider(): string
{
    return WakeupServiceProvider::class;
}

function wakeup_bridge_sweeper(): string
{
    return DueWakeupSweeper::class;
}

function wakeup_bridge_options(): string
{
    return WakeupOptions::class;
}

function wakeup_bridge_registry(): string
{
    return WakeupFixtures::registryClass();
}

function wakeup_bridge_system_clock(): string
{
    return WakeupFixtures::systemClockClass();
}

function wakeup_bridge_schema(): string
{
    return WakeupFixtures::SCHEMA;
}

function wakeup_bridge_point(): string
{
    return WakeupFixtures::POINT;
}

function wakeup_bridge_register(): void
{
    app()->register(WakeupServiceProvider::class);
}

function wakeup_bridge_register_bindings(): void
{
    new WakeupServiceProvider(app())->register();
}

function wakeup_bridge_sweep(object $context, int $limit): object
{
    return resolve(DueWakeupSweeper::class)->sweep($context, $limit);
}
