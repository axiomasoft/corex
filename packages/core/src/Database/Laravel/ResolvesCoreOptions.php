<?php

declare(strict_types=1);

namespace CoreX\Database\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Config\Data\CoreOptions;
use Illuminate\Container\Container;

/**
 * Bridge for Eloquent models of the kernel: a model is built by the framework and cannot take
 * constructor dependencies, so the configured table name is read here, inside the bridge.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
trait ResolvesCoreOptions
{
    protected function coreOptions(): CoreOptions
    {
        return Container::getInstance()->make(CoreOptions::class);
    }
}
