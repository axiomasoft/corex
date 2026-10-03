<?php

declare(strict_types=1);

namespace CoreX\Notifications;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Modules\Contracts\PackageRegistrar;
use CoreX\Notifications\Delivery\Internal\Commands\SweepNotificationDeliveries;
use CoreX\Notifications\Errors\Internal\PackageProblemDefinitions;
use CoreX\Notifications\Laravel\NotificationsBindingsRegistrar;
use CoreX\Notifications\Laravel\NotificationsDigestRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class NotificationsServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [NotificationsBindingsRegistrar::class, NotificationsDigestRegistrar::class];

    #[Override]
    public function register(): void
    {
        ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-notifications.php', 'corex-notifications');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([SweepNotificationDeliveries::class]);
        }
    }

    public function boot(): void
    {
        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }
    }
}
