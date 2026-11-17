<?php

declare(strict_types=1);

namespace CoreX\Panels;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use Illuminate\Support\ServiceProvider;
use Override;
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
