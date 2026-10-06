<?php

declare(strict_types=1);

namespace CoreX\AccessAzguard;

use CoreX\AccessAzguard\Internal\AccessAzguardOptions;
use CoreX\AccessAzguard\Internal\AccessAzguardSchemaSets;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Contracts\SchemaSetProvider;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class AccessAzguardServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([AccessAzguardSchemaSets::class], SchemaSetProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-access-azguard.php', 'corex-access-azguard');

        (new AccessAzguardRegistrar)->register($this->app);
    }

    public function boot(): void
    {
        $this->app->make(AccessAzguardOptions::class);

        (new AccessAzguardRegistrar)->boot($this->app);

        $this->publishes([
            __DIR__.'/../config/corex-access-azguard.php' => config_path('corex-access-azguard.php'),
        ], 'corex-access-azguard-config');

    }
}
