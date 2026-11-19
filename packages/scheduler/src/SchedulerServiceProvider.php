<?php

declare(strict_types=1);

namespace CoreX\Scheduler;

use CoreX\Scheduler\Internal\Commands\SweepWakeups;
use CoreX\Scheduler\Internal\Commands\ValidateWakeupStamps;
use CoreX\Scheduler\Laravel\WakeupBindingsRegistrar;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class SchedulerServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [WakeupBindingsRegistrar::class];

    #[Override]
    public function register(): void
    {
        $this->app->tag((new SchedulerModule)->schemaSetProviders(), SchemaSetProvider::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-scheduler.php', 'corex-scheduler');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }

        if ($this->app->runningInConsole()) {
            $this->app->make(Dispatcher::class)->listen(ArtisanStarting::class, static fn (ArtisanStarting $event) => $event->artisan->resolveCommands([SweepWakeups::class, ValidateWakeupStamps::class]));
        }
    }

    public function boot(): void
    {
        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }
    }
}
