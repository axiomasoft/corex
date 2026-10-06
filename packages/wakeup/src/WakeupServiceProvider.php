<?php

declare(strict_types=1);

namespace CoreX\Wakeup;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Database\Contracts\SchemaSetProvider;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Wakeup\Internal\Commands\SweepWakeups;
use CoreX\Wakeup\Internal\Commands\ValidateWakeupStamps;
use CoreX\Wakeup\Internal\WakeupSchemaSets;
use CoreX\Wakeup\Laravel\WakeupBindingsRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WakeupServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [WakeupBindingsRegistrar::class];

    #[Override]
    public function register(): void
    {
        $this->app->tag([WakeupSchemaSets::class], SchemaSetProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-wakeup.php', 'corex-wakeup');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SweepWakeups::class, ValidateWakeupStamps::class]);
        }
    }

    public function boot(): void
    {
        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }
    }
}
