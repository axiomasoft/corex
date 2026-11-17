<?php

declare(strict_types=1);

use CoreX\Scheduler\Internal\SchedulerOptions;
use CoreX\Scheduler\SchedulerServiceProvider;
use CoreX\Scheduler\Testing\WakeupFixtures;
use CoreX\Scheduling\Contracts\DueWakeupSweeper;

function wakeup_bridge_provider(): string
{
    return SchedulerServiceProvider::class;
}

function wakeup_bridge_sweeper(): string
{
    return DueWakeupSweeper::class;
}

function wakeup_bridge_options(): string
{
    return SchedulerOptions::class;
}

function wakeup_bridge_registry(): string
{
    return WakeupFixtures::registryClass();
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
    app()->register(SchedulerServiceProvider::class);
}

function wakeup_bridge_register_bindings(): void
{
    new SchedulerServiceProvider(app())->register();
}

function wakeup_bridge_sweep(object $context, int $limit): object
{
    return resolve(DueWakeupSweeper::class)->sweep($context, $limit);
}
