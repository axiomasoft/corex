<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Feature;

use CoreX\Api\ApiServiceProvider;
use CoreX\Api\Contracts\WebhookSigner;
use CoreX\Api\Data\WebhookOptions;
use CoreX\Api\Webhooks\WebhooksServiceProvider;
use CoreX\CoreServiceProvider;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Support\InvalidConfiguration;
use Orchestra\Testbench\TestCase;

final class WebhookLoadContourBootTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
    }

    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, ApiServiceProvider::class, WebhooksServiceProvider::class];
    }

    public function test_boot_disables_remote_delivery_and_reports_missing_signing(): void
    {
        self::assertFalse($this->app->make(WebhookOptions::class)->deliveryEnabled);
        self::assertFalse($this->app->make(WebhookOptions::class)->workerReady);
        self::assertFalse($this->app->bound(WebhookSigner::class));

        $this->app['config']->set('corex-webhooks.delivery_enabled', true);
        $this->app['config']->set('corex-webhooks.worker_ready', false);

        try {
            $provider = $this->app->getProvider(WebhooksServiceProvider::class);
            self::assertInstanceOf(WebhooksServiceProvider::class, $provider);
            $provider->register();
            self::fail('Remote delivery booted without a ready worker.');
        } catch (InvalidConfiguration $exception) {
            self::assertStringContainsString('worker_ready', $exception->getMessage());
        }

        self::assertFalse($this->app->make(WebhookOptions::class)->deliveryEnabled);
    }
}
