<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Support\ServiceProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccessAzguardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        (new AccessAzguardRegistrar)->register($this->app);
    }
}
