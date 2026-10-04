<?php

declare(strict_types=1);

namespace CoreX\Webhooks;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Laravel\ProblemDefinitions;
use CoreX\Operations\OpenApi\Contracts\ContractSnapshotSection;
use CoreX\Webhooks\Data\WebhookOptions;
use CoreX\Webhooks\Errors\Internal\PackageProblemDefinitions;
use CoreX\Webhooks\Internal\Commands\DeliverWebhooks;
use CoreX\Webhooks\Internal\Commands\PruneWebhooks;
use CoreX\Webhooks\Internal\WebhookSnapshotSection;
use CoreX\Webhooks\Laravel\WebhookBindingsRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;

/** Explicit webhook composition; remote delivery remains opt-in. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class WebhooksServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        ProblemDefinitions::register($this->app, __DIR__.'/../composer.json', PackageProblemDefinitions::class);

        $this->mergeConfigFrom(__DIR__.'/../config/corex-webhooks.php', 'corex-webhooks');
        (new WebhookBindingsRegistrar)->register($this->app);
        $this->app->tag([WebhookSnapshotSection::class], ContractSnapshotSection::class);
    }

    public function boot(): void
    {
        (new WebhookBindingsRegistrar)->boot($this->app);

        if ($this->app->runningInConsole()) {
            $commands = [PruneWebhooks::class];

            if ($this->app->make(WebhookOptions::class)->deliveryEnabled) {
                $commands[] = DeliverWebhooks::class;
            }

            $this->commands($commands);
        }

        $this->publishes([__DIR__.'/../config/corex-webhooks.php' => config_path('corex-webhooks.php')], 'corex-webhooks-config');
        $this->publishes([__DIR__.'/../database/migrations/tenant/create_sys_api_webhook_tables.php' => database_path('migrations/tenant/create_sys_api_webhook_tables.php')], 'corex-webhooks-migrations-tenant');
    }
}
