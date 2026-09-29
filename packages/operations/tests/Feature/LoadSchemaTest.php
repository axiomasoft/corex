<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Feature;

use CoreX\Operations\ApiServiceProvider;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\Group;

final class LoadSchemaTest extends TestCase
{
    /** @var list<string> */
    private const array BudgetTables = [
        'sys_api_budget_buckets',
        'sys_api_budget_reservations',
    ];

    /** @var list<string> */
    private const array TenantTables = [
        'sys_api_bulk_operations',
        'sys_api_webhook_subscriptions',
        'sys_api_webhook_deliveries',
        'sys_api_webhook_attempts',
    ];

    protected function tearDown(): void
    {
        foreach (['api_budget', 'api_tenant_a', 'api_tenant_b', 'api_pg_budget', 'api_pg_tenant'] as $name) {
            DB::purge($name);
        }

        parent::tearDown();
    }

    public function test_publication_is_inert_and_requires_explicit_schema_identity(): void
    {
        self::assertFalse(DB::connection('sqlite')->getSchemaBuilder()->hasTable('sys_api_budget_buckets'));

        $budget = ApiServiceProvider::pathsToPublish(ApiServiceProvider::class, 'corex-api-budget-migrations');
        $tenant = ApiServiceProvider::pathsToPublish(ApiServiceProvider::class, 'corex-api-tenant-migrations');

        self::assertSame([
            realpath(__DIR__.'/../../database/migrations/budget') => database_path('migrations/budget'),
        ], array_combine(array_map('realpath', array_keys($budget)), array_values($budget)));
        self::assertSame([
            realpath(__DIR__.'/../../database/migrations/tenant') => database_path('migrations/tenant'),
        ], array_combine(array_map('realpath', array_keys($tenant)), array_values($tenant)));

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(ApiSchemaRegistrar::class);
    }

    public function test_sqlite_schemas_constraints_isolation_and_rollback(): void
    {
        foreach (['api_budget', 'api_tenant_a', 'api_tenant_b'] as $name) {
            $this->configureSqlite($name);
        }

        $this->selectSchema('api_budget', 'api_tenant_a');
        [$budgetMigration, $bulkA, $webhookA] = $this->migrateAll();

        $budget = DB::connection('api_budget');
        $tenantA = DB::connection('api_tenant_a');
        $tenantB = DB::connection('api_tenant_b');

        $this->assertTables($budget, self::BudgetTables);
        $this->assertTables($tenantA, self::TenantTables);
        $this->assertMissingTables($budget, self::TenantTables);
        $this->assertMissingTables($tenantA, self::BudgetTables);
        $this->assertMissingTables($tenantB, [...self::BudgetTables, ...self::TenantTables]);
        $this->assertColumnsAndIndexes($budget, $tenantA);
        $this->assertNegativeFixtures($budget, $tenantA);

        $tenantA->table('sys_api_bulk_operations')->insert($this->bulkRow('bulk-a'));
        $this->insertWebhookGraph($tenantA, 'a');

        $this->selectSchema('api_budget', 'api_tenant_b');
        [, $bulkB, $webhookB] = $this->migrateAll(includeBudget: false);
        self::assertSame(0, $tenantB->table('sys_api_bulk_operations')->count());
        self::assertSame(0, $tenantB->table('sys_api_webhook_deliveries')->count());
        self::assertSame(1, $tenantA->table('sys_api_bulk_operations')->count());
        self::assertSame(1, $tenantA->table('sys_api_webhook_deliveries')->count());

        $this->expectQueryFailure(fn (): bool => $tenantA->table('sys_api_bulk_operations')->insert($this->bulkRow('bulk-duplicate')));

        foreach ([$budget, $tenantA, $tenantB] as $connection) {
            $connection->getSchemaBuilder()->create('api_schema_sentinel', static function (Blueprint $table): void {
                $table->string('id')->primary();
            });
        }

        $this->runDown($webhookB);
        $this->runDown($bulkB);
        $this->selectSchema('api_budget', 'api_tenant_a');
        $this->runDown($webhookA);
        $this->runDown($bulkA);
        $this->runDown($budgetMigration);

        $this->assertMissingTables($budget, self::BudgetTables);
        $this->assertMissingTables($tenantA, self::TenantTables);
        $this->assertMissingTables($tenantB, self::TenantTables);
        foreach ([$budget, $tenantA, $tenantB] as $connection) {
            self::assertTrue($connection->getSchemaBuilder()->hasTable('api_schema_sentinel'));
        }
    }

    #[Group('pg')]
    public function test_postgresql_schema_and_constraints(): void
    {
        $database = getenv('PGSQL_DATABASE') ?: '';

        if (preg_match('/\A[a-z0-9_]+_test\z/D', $database) !== 1) {
            self::markTestSkipped('PGSQL_DATABASE must name an isolated *_test database.');
        }

        $base = [
            'driver' => 'pgsql',
            'host' => getenv('PGSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('PGSQL_PORT') ?: '5434',
            'database' => $database,
            'username' => getenv('PGSQL_USERNAME') ?: 'corex',
            'password' => getenv('PGSQL_PASSWORD') ?: 'corex',
            'charset' => 'utf8',
            'prefix' => '',
        ];
        $rootName = 'api_pg_root';
        $this->app['config']->set('database.connections.'.$rootName, $base);
        DB::purge($rootName);
        $root = DB::connection($rootName);
        $root->statement('DROP SCHEMA IF EXISTS api_p143_budget_test CASCADE');
        $root->statement('DROP SCHEMA IF EXISTS api_p143_tenant_test CASCADE');
        $root->statement('CREATE SCHEMA api_p143_budget_test');
        $root->statement('CREATE SCHEMA api_p143_tenant_test');

        try {
            $this->app['config']->set('database.connections.api_pg_budget', $base + ['search_path' => 'api_p143_budget_test']);
            $this->app['config']->set('database.connections.api_pg_tenant', $base + ['search_path' => 'api_p143_tenant_test']);
            DB::purge('api_pg_budget');
            DB::purge('api_pg_tenant');
            $this->selectSchema('api_pg_budget', 'api_pg_tenant');
            [$budgetMigration, $bulkMigration, $webhookMigration] = $this->migrateAll();
            $budget = DB::connection('api_pg_budget');
            $tenant = DB::connection('api_pg_tenant');

            $this->assertTables($budget, self::BudgetTables);
            $this->assertTables($tenant, self::TenantTables);
            $this->assertColumnsAndIndexes($budget, $tenant);
            $this->assertNegativeFixtures($budget, $tenant);

            $this->runDown($webhookMigration);
            $this->runDown($bulkMigration);
            $this->runDown($budgetMigration);
            $this->assertMissingTables($budget, self::BudgetTables);
            $this->assertMissingTables($tenant, self::TenantTables);
        } finally {
            DB::disconnect('api_pg_budget');
            DB::disconnect('api_pg_tenant');
            $root->statement('DROP SCHEMA IF EXISTS api_p143_budget_test CASCADE');
            $root->statement('DROP SCHEMA IF EXISTS api_p143_tenant_test CASCADE');
            DB::purge($rootName);
        }
    }

    private function configureSqlite(string $name): void
    {
        $this->app['config']->set('database.connections.'.$name, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
    }

    private function selectSchema(string $budget, string $tenant): void
    {
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-api.schema', [
            'budget' => [
                'connection' => $budget,
                'driver' => (string) $this->app['config']->get('database.connections.'.$budget.'.driver'),
                'database' => (string) $this->app['config']->get('database.connections.'.$budget.'.database'),
            ],
            'tenant' => [
                'connection' => $tenant,
                'driver' => (string) $this->app['config']->get('database.connections.'.$tenant.'.driver'),
                'database' => (string) $this->app['config']->get('database.connections.'.$tenant.'.database'),
            ],
        ]);
    }

    /** @return array{Migration, Migration, Migration} */
    private function migrateAll(bool $includeBudget = true): array
    {
        $budget = require __DIR__.'/../../database/migrations/budget/create_sys_api_budget_tables.php';
        $bulk = require __DIR__.'/../../database/migrations/tenant/create_sys_api_bulk_operations_table.php';
        $webhook = require __DIR__.'/../../../webhooks/database/migrations/tenant/create_sys_api_webhook_tables.php';

        if ($includeBudget) {
            $budget->up();
        }
        $bulk->up();
        $webhook->up();

        return [$budget, $bulk, $webhook];
    }

    /** @param list<string> $tables */
    private function assertTables(Connection $connection, array $tables): void
    {
        foreach ($tables as $table) {
            self::assertTrue($connection->getSchemaBuilder()->hasTable($table), $table);
        }
    }

    /** @param list<string> $tables */
    private function assertMissingTables(Connection $connection, array $tables): void
    {
        foreach ($tables as $table) {
            self::assertFalse($connection->getSchemaBuilder()->hasTable($table), $table);
        }
    }

    private function assertColumnsAndIndexes(Connection $budget, Connection $tenant): void
    {
        self::assertSame(
            ['key', 'capacity', 'available', 'refill_rate', 'last_refill_at', 'last_used_at'],
            $budget->getSchemaBuilder()->getColumnListing('sys_api_budget_buckets'),
        );
        self::assertSame(
            ['id', 'keys', 'estimate', 'charged', 'recipe_hash', 'state', 'created_at', 'expires_at'],
            $budget->getSchemaBuilder()->getColumnListing('sys_api_budget_reservations'),
        );
        self::assertContains('sys_api_budget_reservations_retention_idx', array_column($budget->getSchemaBuilder()->getIndexes('sys_api_budget_reservations'), 'name'));
        self::assertContains('sys_api_bulk_operations_submission_unique', array_column($tenant->getSchemaBuilder()->getIndexes('sys_api_bulk_operations'), 'name'));
        self::assertContains('sys_api_bulk_operations_claim_idx', array_column($tenant->getSchemaBuilder()->getIndexes('sys_api_bulk_operations'), 'name'));
        self::assertContains('sys_api_webhook_subscriptions_lookup_idx', array_column($tenant->getSchemaBuilder()->getIndexes('sys_api_webhook_subscriptions'), 'name'));
        self::assertContains('sys_api_webhook_deliveries_event_unique', array_column($tenant->getSchemaBuilder()->getIndexes('sys_api_webhook_deliveries'), 'name'));
        self::assertContains('sys_api_webhook_deliveries_claim_idx', array_column($tenant->getSchemaBuilder()->getIndexes('sys_api_webhook_deliveries'), 'name'));
        self::assertContains('sys_api_webhook_attempts_number_unique', array_column($tenant->getSchemaBuilder()->getIndexes('sys_api_webhook_attempts'), 'name'));
        self::assertContains('sys_api_webhook_attempts_delivery_idx', array_column($tenant->getSchemaBuilder()->getIndexes('sys_api_webhook_attempts'), 'name'));
    }

    private function assertNegativeFixtures(Connection $budget, Connection $tenant): void
    {
        $now = '2026-09-25 10:00:00.123456+00:00';
        $this->expectQueryFailure(fn (): bool => $budget->table('sys_api_budget_buckets')->insert([
            'key' => str_repeat('a', 64), 'capacity' => -1, 'available' => 0, 'refill_rate' => 1,
            'last_refill_at' => $now, 'last_used_at' => $now,
        ]));
        $this->expectQueryFailure(fn (): bool => $budget->table('sys_api_budget_reservations')->insert([
            'id' => 'bad-keys', 'keys' => json_encode(['one'], JSON_THROW_ON_ERROR), 'estimate' => 1, 'charged' => 0,
            'recipe_hash' => str_repeat('b', 64), 'state' => 'held', 'created_at' => $now, 'expires_at' => $now,
        ]));
        $this->expectQueryFailure(fn (): bool => $tenant->table('sys_api_bulk_operations')->insert($this->bulkRow('invalid', 'invented')));
        $this->expectQueryFailure(fn (): bool => $tenant->table('sys_api_webhook_subscriptions')->insert($this->subscriptionRow('invalid', 'invented')));
    }

    /** @return array<string, mixed> */
    private function bulkRow(string $id, string $status = 'pending'): array
    {
        $now = '2026-09-25 10:00:00.123456+00:00';

        return [
            'id' => $id, 'major' => 'v1', 'operation_id' => 'orders.import', 'submission_id' => 'submission-1',
            'scope' => '{}', 'actor_id' => 'actor-1', 'client_id' => 'client-1', 'contract_hash' => str_repeat('c', 64),
            'extension_stamp' => '{}', 'input_ref' => '{}', 'status' => $status, 'revision' => 0,
            'checkpoint' => '{}', 'progress' => '{}', 'attempt' => 0, 'next_attempt_at' => $now, 'created_at' => $now,
        ];
    }

    /** @return array<string, mixed> */
    private function subscriptionRow(string $id, string $status = 'active'): array
    {
        $now = '2026-09-25 10:00:00.123456+00:00';

        return [
            'id' => $id, 'revision' => 1, 'major' => 'v1', 'topic' => 'orders.changed', 'topic_version' => 1,
            'endpoint_url' => 'https://receiver.test/hook', 'status' => $status, 'max_pii_class' => 'pii-pseudonymous',
            'key_refs' => '[]', 'created_at' => $now, 'updated_at' => $now,
        ];
    }

    private function insertWebhookGraph(Connection $connection, string $suffix): void
    {
        $now = '2026-09-25 10:00:00.123456+00:00';
        $connection->table('sys_api_webhook_subscriptions')->insert($this->subscriptionRow('subscription-'.$suffix));
        $connection->table('sys_api_webhook_deliveries')->insert([
            'id' => 'delivery-'.$suffix, 'subscription_id' => 'subscription-'.$suffix, 'subscription_revision' => 1,
            'event_id' => 'event-'.$suffix, 'topic' => 'orders.changed', 'topic_version' => 1, 'major' => 'v1',
            'endpoint_snapshot' => 'https://receiver.test/hook', 'scope' => '{}', 'body' => '{}',
            'body_hash' => str_repeat('d', 64), 'status' => 'pending', 'attempt' => 0, 'replay_count' => 0,
            'next_attempt_at' => $now, 'created_at' => $now, 'expires_at' => $now,
        ]);
        $connection->table('sys_api_webhook_attempts')->insert([
            'id' => 'attempt-'.$suffix, 'delivery_id' => 'delivery-'.$suffix, 'attempt' => 1,
            'lease_owner' => 'worker-1', 'started_at' => $now, 'outcome' => 'succeeded',
        ]);
    }

    private function runDown(Migration $migration): void
    {
        if (! is_callable([$migration, 'down'])) {
            throw new LogicException('API schema migration must provide down().');
        }

        call_user_func([$migration, 'down']);
    }

    /** @param callable(): mixed $operation */
    private function expectQueryFailure(callable $operation): void
    {
        try {
            $operation();
            self::fail('Expected the database constraint to reject the fixture.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }
}
