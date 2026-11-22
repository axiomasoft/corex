<?php

declare(strict_types=1);

namespace CoreX\Webhooks;

use CoreX\Webhooks\Data\WebhookOptions;
use CoreX\Webhooks\Errors\Internal\PackageProblemDefinitions;
use CoreX\Webhooks\Internal\Commands\DeliverWebhooks;
use CoreX\Webhooks\Internal\Commands\PruneWebhooks;
use CoreX\Webhooks\Internal\WebhooksSchemaSets;
use CoreX\Webhooks\Laravel\WebhookBindingsRegistrar;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Errors\Laravel\ProblemDefinitions;
use Packman\Schema\Contracts\Migration\SchemaSetProvider;

/** Explicit webhook composition; remote delivery remains opt-in. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WebhooksServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->tag([WebhooksSchemaSets::class], SchemaSetProvider::class);

        ProblemDefinitions::register(app: $this->app, composerManifest: __DIR__.'/../composer.json', provider: PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-webhooks.php', 'corex-webhooks');
        (new WebhookBindingsRegistrar)->register($this->app);
    }

    public function boot(): void
    {
        (new WebhookBindingsRegistrar)->boot($this->app);

        if ($this->app->runningInConsole()) {
            $commands = [PruneWebhooks::class];

            if ($this->app->make(WebhookOptions::class)->deliveryEnabled) {
                $commands[] = DeliverWebhooks::class;
            }

            $this->app->make(Dispatcher::class)->listen(ArtisanStarting::class, static fn (ArtisanStarting $event) => $event->artisan->resolveCommands($commands));
        }

        $this->publishes([__DIR__.'/../config/corex-webhooks.php' => config_path('corex-webhooks.php')], 'corex-webhooks-config');

    }
}
