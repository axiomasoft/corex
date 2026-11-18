<?php

declare(strict_types=1);

namespace CoreX\Panels;

use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Panels\Laravel\PanelsServiceProvider as PackmanPanelsServiceProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class PanelsServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->register(PackmanPanelsServiceProvider::class);
    }
}
