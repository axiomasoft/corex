<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\TenantContext;
use CoreX\Time\Laravel\SystemClock;
use DateTimeImmutable;
use Illuminate\Container\Container;
use Psr\Clock\ClockInterface;

/**
 * Bridge of the `DomainEvent` constructor: an event is built with `new` anywhere in host code and
 * cannot carry dependencies, so its default tenant and occurrence time come from the running application.
 *
 * @internal Composition helper of the messaging module.
 */
final class DomainEventDefaults
{
    public static function tenant(): TenantContext
    {
        return Container::getInstance()->make(TenantContextResolver::class)->current();
    }

    public static function now(): DateTimeImmutable
    {
        $container = Container::getInstance();

        // An event constructed with an explicit tenant must keep working without a bound clock (standalone use).
        $clock = $container->bound(ClockInterface::class) ? $container->make(ClockInterface::class) : new SystemClock;

        return $clock->now();
    }
}
