<?php

declare(strict_types=1);

namespace CoreX\Notifications\Tests;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Notifications\Internal\NotificationsOptions;
use CoreX\Notifications\NotificationsServiceProvider;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Illuminate\Support\Facades\Schema;
use Packman\Schema\Testing\MigratesSchemaSets;

require_once __DIR__.'/../../scheduler/tests/Support/PackageBridge.php';
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Override;
use Packman\PackmanServiceProvider;
use Packman\Schema\Laravel\SchemaServiceProvider;

abstract class TestCase extends Orchestra
{
    use MigratesSchemaSets;

    #[Override]
    protected function getPackageProviders($app): array
    {
        // Package registration runs before defineEnvironment, and the digest tags are chosen then.
        $app['config']->set('corex-notifications.digest.enabled', true);

        return [PackmanServiceProvider::class, SchemaServiceProvider::class, wakeup_bridge_provider(), NotificationsServiceProvider::class];
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('n', 32)));
        $app['config']->set('corex-notifications.digest.enabled', true);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::dropIfExists('ntf_deliveries');
        Schema::dropIfExists('ntf_recipient_state');
        Schema::dropIfExists('ntf_notifications');
        Schema::dropIfExists('sys_wakeup_requests');
        $this->migrateSchemaSets(['axiomasoft/corex-scheduler:tenant'], DB::getDefaultConnection());
        $this->migrateSchemaSets(['axiomasoft/corex-notifications:tenant'], DB::getDefaultConnection());
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $contexts = new NotificationTenantContextResolver;
        $this->app->instance(TenantContextResolver::class, $contexts);
        $this->app->instance(DataScopeCodec::class, new ArrayDataScopeCodec);
        CallerWorkScopeFixture::bind($this->app, CallerWorkScopeFixture::forContext(
            $contexts->current(), '11111111-1111-7111-8111-111111111112', str_repeat('a', 64),
        ));
        $this->app->forgetInstance(NotificationsOptions::class);
        $this->app->forgetScopedInstances();
    }
}
