<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel\Support;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Container\Container;

/** Framework-created Eloquent models resolve their central configuration through this bridge. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
trait ResolvesTenancyOptions
{
    protected function tenancyOptions(): TenancyOptions
    {
        return Container::getInstance()->make(TenancyOptions::class);
    }
}
