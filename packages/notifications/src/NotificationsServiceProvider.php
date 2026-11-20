<?php

declare(strict_types=1);

namespace CoreX\Notifications;

use CoreX\Context\Laravel\WorkQueueHandlers;
use CoreX\Notifications\Delivery\Internal\Commands\SweepNotificationDeliveries;
use CoreX\Notifications\Delivery\Internal\Jobs\DeliverNotification;
use CoreX\Notifications\Errors\Internal\PackageProblemDefinitions;
use CoreX\Notifications\Laravel\NotificationsBindingsRegistrar;
use CoreX\Notifications\Laravel\NotificationsDigestRegistrar;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Laravel\ProblemDefinitions;
use Packman\Foundation\Contracts\PackageRegistrar;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class NotificationsServiceProvider extends ServiceProvider
{
    /** @var list<class-string<PackageRegistrar>> */
    private const array REGISTRARS = [NotificationsBindingsRegistrar::class, NotificationsDigestRegistrar::class];

    #[Override]
    public function register(): void
    {
        $this->callAfterResolving(WorkQueueHandlers::class, static fn (WorkQueueHandlers $handlers) => $handlers->tenant(DeliverNotification::class));

        $this->app->tag((new NotificationsModule)->schemaSetProviders(), SchemaSetProvider::class);

        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-notifications.php', 'corex-notifications');

        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->register($this->app);
        }

        if ($this->app->runningInConsole()) {
            $this->app->make(Dispatcher::class)->listen(ArtisanStarting::class, static fn (ArtisanStarting $event) => $event->artisan->resolveCommands([SweepNotificationDeliveries::class]));
        }
    }

    public function boot(): void
    {
        foreach (self::REGISTRARS as $registrar) {
            (new $registrar)->boot($this->app);
        }
    }
}
