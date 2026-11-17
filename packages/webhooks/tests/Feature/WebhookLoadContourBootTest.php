<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\CoreServiceProvider;
use CoreX\Operations\OperationsServiceProvider;
use CoreX\Webhooks\Contracts\WebhookSigner;
use CoreX\Webhooks\Data\WebhookOptions;
use CoreX\Webhooks\WebhooksServiceProvider;
use Orchestra\Testbench\TestCase;
use Packman\Modules\ModulesServiceProvider;

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
        return [CoreServiceProvider::class, ModulesServiceProvider::class, OperationsServiceProvider::class, WebhooksServiceProvider::class];
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
