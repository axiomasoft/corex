<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Internal\SqlWebhookDeliveryStore;
use CoreX\Webhooks\Internal\WebhookRetryPolicy;
use CoreX\Webhooks\Laravel\WebhookOptionsParser;
use CoreX\Webhooks\Tests\TestCase;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Throwable;

final class WebhookConcurrencyTest extends TestCase
{
    #[Group('pg')]
    public function test_two_workers_claim_once_and_late_ack_cannot_overwrite_the_new_lease(): void
    {
        if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
            self::markTestSkipped('The PostgreSQL webhook concurrency proof requires pcntl and Unix sockets.');
        }

        $database = getenv('PGSQL_DATABASE') ?: '';

        if (preg_match('/\A[a-z0-9_]+_test\z/D', $database) !== 1) {
            self::markTestSkipped('PGSQL_DATABASE must name an isolated *_test database.');
        }

        $root = $this->configurePostgres($database);
        $root->statement('DROP SCHEMA IF EXISTS api_p1415_tenant_test CASCADE');
        $root->statement('CREATE SCHEMA api_p1415_tenant_test');

        try {
            $this->selectSchema($database);
            $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_webhook_tables.php';
            $migration->up();
            $connection = DB::connection('api_pg_tenant');
            $clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
            $scope = $this->scope($connection);
            $this->seedDelivery($connection, $clock->now());
            $claims = $this->raceClaims($database, $scope);

            self::assertCount(1, array_filter($claims));
            self::assertSame(1, $connection->table('sys_api_webhook_attempts')->count());

            $firstWorker = (string) array_key_first(array_filter($claims));
            $first = $this->store($connection, $clock)->find($scope, 'delivery-pg');
            self::assertNotNull($first);
            self::assertSame($firstWorker, $first->leaseOwner);

            $clock->advance(new DateInterval('PT31S'));
            $store = $this->store($connection, $clock);
            $replacement = $store->claim($scope, 'worker-replacement');
            self::assertNotNull($replacement);
            self::assertSame(2, $replacement->attempt);
            self::assertFalse($store->finish($first, new WebhookHttpResult(204, null, null, 1)));
            self::assertTrue($store->finish($replacement, new WebhookHttpResult(204, null, null, 1)));
            self::assertSame('succeeded', $store->find($scope, 'delivery-pg')?->status);
            self::assertSame(
                ['unknown', 'succeeded'],
                $connection->table('sys_api_webhook_attempts')->orderBy('attempt')->pluck('outcome')->all(),
            );
        } finally {
            DB::disconnect('api_pg_tenant');
            $root->statement('DROP SCHEMA IF EXISTS api_p1415_tenant_test CASCADE');
            DB::purge('api_pg_root');
        }
    }

    /** @return array<string, bool> */
    private function raceClaims(string $database, DataScope $scope): array
    {
        $children = [];

        foreach (['worker-a', 'worker-b'] as $worker) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            self::assertNotFalse($pair);
            $pid = pcntl_fork();
            self::assertNotSame(-1, $pid);

            if ($pid === 0) {
                fclose($pair[0]);

                try {
                    DB::purge('api_pg_tenant');
                    $connection = DB::connection('api_pg_tenant');
                    $clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00.000000+00:00'));
                    fwrite($pair[1], "ready\n");
                    fgets($pair[1]);
                    $claimed = $this->store($connection, $clock)->claim($scope, $worker);
                    fwrite($pair[1], json_encode(['worker' => $worker, 'claimed' => $claimed !== null], JSON_THROW_ON_ERROR)."\n");
                } catch (Throwable $exception) {
                    fwrite($pair[1], json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n");
                }

                fclose($pair[1]);
                exit(0);
            }

            fclose($pair[1]);
            $children[] = ['pid' => $pid, 'socket' => $pair[0]];
        }

        foreach ($children as $child) {
            self::assertSame("ready\n", fgets($child['socket']));
        }

        foreach ($children as $child) {
            fwrite($child['socket'], "go\n");
        }

        $claims = [];

        foreach ($children as $child) {
            $result = json_decode((string) fgets($child['socket']), true, flags: JSON_THROW_ON_ERROR);
            fclose($child['socket']);
            pcntl_waitpid($child['pid'], $status);
            self::assertSame(0, pcntl_wexitstatus($status));
            self::assertArrayNotHasKey('error', $result, $result['error'] ?? 'PostgreSQL child failed.');
            $claims[$result['worker']] = $result['claimed'];
        }

        return $claims;
    }

    private function store(Connection $connection, FrozenClock $clock): SqlWebhookDeliveryStore
    {
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-1', 'account', 'active', [], []));

        return new SqlWebhookDeliveryStore(
            $connection,
            new GuardedTenantContextRestorer($contexts),
            $clock,
            new WebhookRetryPolicy(WebhookOptionsParser::fromArray([]), static fn (int $min, int $max): int => $max),
            30,
        );
    }

    private function seedDelivery(Connection $connection, DateTimeImmutable $now): void
    {
        $connection->table('sys_api_webhook_subscriptions')->insert([
            'id' => 'subscription-pg', 'revision' => 0, 'major' => 'v1', 'topic' => 'orders.placed',
            'topic_version' => 1, 'endpoint_url' => 'https://hooks.example/receiver', 'status' => 'active',
            'max_pii_class' => 'personal', 'key_refs' => '["current"]', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $body = JsonCanonical::encode(['event_id' => 'event-pg', 'data' => ['id' => 'order-1']]);
        $connection->table('sys_api_webhook_deliveries')->insert([
            'id' => 'delivery-pg', 'subscription_id' => 'subscription-pg', 'subscription_revision' => 0,
            'event_id' => 'event-pg', 'topic' => 'orders.placed', 'topic_version' => 1, 'major' => 'v1',
            'endpoint_snapshot' => 'https://hooks.example/receiver',
            'scope' => JsonCanonical::encode(['account' => 'account-1']), 'body' => $body,
            'body_hash' => hash('sha256', $body), 'status' => 'pending', 'attempt' => 0, 'replay_count' => 0,
            'lease_owner' => null, 'lease_until' => null, 'next_attempt_at' => $now, 'created_at' => $now,
            'finished_at' => null, 'expires_at' => $now->add(new DateInterval('P7D')), 'last_error' => null,
        ]);
    }

    private function configurePostgres(string $database): Connection
    {
        $base = [
            'driver' => 'pgsql', 'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434', 'database' => $database,
            'username' => getenv('PGSQL_USERNAME') ?: 'corex', 'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8', 'prefix' => '',
        ];
        $this->app['config']->set('database.connections.api_pg_root', $base + ['search_path' => 'public']);
        $this->app['config']->set('database.connections.api_pg_tenant', $base + ['search_path' => 'api_p1415_tenant_test']);
        DB::purge('api_pg_root');
        DB::purge('api_pg_tenant');

        return DB::connection('api_pg_root');
    }

    private function selectSchema(string $database): void
    {
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('database.connections.api_budget_stub', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_pg_tenant', 'driver' => 'pgsql', 'database' => $database],
        ]);
    }

    private function scope(Connection $connection): DataScope
    {
        return new DataScope(
            'account-1', null, 'pgsql:'.$connection->getDatabaseName(), 'actor-1',
            hash('sha256', 'webhook-pg-restore'),
        );
    }
}
