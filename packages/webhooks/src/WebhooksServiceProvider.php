<?php

declare(strict_types=1);

namespace CoreX\Webhooks;

use CoreX\Operations\OpenApi\Contracts\ContractSnapshotSection;
use CoreX\Webhooks\Data\WebhookOptions;
use CoreX\Webhooks\Internal\Commands\DeliverWebhooks;
use CoreX\Webhooks\Internal\Commands\PruneWebhooks;
use CoreX\Webhooks\Internal\WebhookSnapshotSection;
use CoreX\Webhooks\Laravel\WebhookRegistrar;
use Illuminate\Support\ServiceProvider;
use Override;

/** Explicit webhook composition; remote delivery remains opt-in. */
final class WebhooksServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-webhooks.php', 'corex-webhooks');
        WebhookRegistrar::register($this->app);
        $this->app->tag([WebhookSnapshotSection::class], ContractSnapshotSection::class);
    }

    public function boot(): void
    {
        WebhookRegistrar::boot($this->app);

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
