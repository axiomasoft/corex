<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\CoreServiceProvider;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Operations\ApiServiceProvider;
use CoreX\Operations\Laravel\Commands\PruneApiOperations;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Webhooks\Data\WebhookOptions;
use CoreX\Webhooks\Internal\WebhookRetention;
use CoreX\Webhooks\WebhooksServiceProvider;
use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Orchestra\Testbench\TestCase;
use ReflectionMethod;

final class WebhooksProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CoreServiceProvider::class, ModulesServiceProvider::class, ApiServiceProvider::class, WebhooksServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('corex-webhooks.delivery_enabled', true);
        $app['config']->set('corex-webhooks.worker_ready', true);
    }

    public function test_webhooks_owns_delivery_and_retention_commands(): void
    {
        $this->app['config']->set('corex-webhooks.delivery_enabled', true);
        $this->app['config']->set('corex-webhooks.worker_ready', true);
        $provider = $this->app->getProvider(WebhooksServiceProvider::class);
        self::assertInstanceOf(WebhooksServiceProvider::class, $provider);
        $provider->register();
        $provider->boot();
        $commands = Artisan::all();

        self::assertArrayHasKey('corex:webhooks:deliver', $commands);
        self::assertArrayHasKey('corex:webhooks:prune', $commands);
        self::assertArrayHasKey('corex:api:prune', $commands);

        foreach ((new ReflectionMethod(PruneApiOperations::class, 'handle'))->getParameters() as $parameter) {
            self::assertNotSame(WebhookRetention::class, (string) $parameter->getType());
        }
    }

    public function test_discovered_webhooks_provider_boots_without_opting_into_engine_routes(): void
    {
        $standalone = new Application(dirname(__DIR__, 2));
        $standalone->instance('config', new Repository);
        $provider = new WebhooksServiceProvider($standalone);
        $provider->register();
        $provider->boot();

        self::assertTrue($standalone->bound(WebhookOptions::class));
        self::assertFalse($standalone->bound(ApiOptions::class));
    }
}
