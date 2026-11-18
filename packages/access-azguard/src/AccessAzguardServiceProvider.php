<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard;

use Illuminate\Support\ServiceProvider;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccessAzguardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        (new AccessAzguardRegistrar)->register($this->app);
    }
}
