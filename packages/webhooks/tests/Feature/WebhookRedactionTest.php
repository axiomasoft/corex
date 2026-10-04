<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Feature;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Internal\GuardedTenantContextRestorer;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Laravel\ApiSchemaRegistrar;
use CoreX\Operations\Pagination\Contracts\CursorKeyring;
use CoreX\Operations\Pagination\Internal\SignedCursorCodec;
use CoreX\Operations\Testing\ContextFixture;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Webhooks\Contracts\WebhookSubscriptionStore;
use CoreX\Webhooks\Internal\WebhookManagement;
use CoreX\Webhooks\Tests\TestCase;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

final class WebhookRedactionTest extends TestCase
{
    public function test_public_attempt_history_has_an_exact_safe_shape(): void
    {
        $this->configure('api_budget_stub');
        $this->configure('api_tenant');
        $this->selectSchema();
        $connection = DB::connection('api_tenant');
        $migration = require __DIR__.'/../../database/migrations/tenant/create_sys_api_webhook_tables.php';
        $migration->up();
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-26T17:00:00.000000+00:00'));
        $contexts = new ContextFixture;
        $contexts->initialize(new AccountRef('account-a', 'account-a', 'active', [], []));
        $scope = new DataScope('account-a', null, 'sqlite::memory:', 'actor-a', hash('sha256', 'webhook-redaction'));
        $this->seedFixture($connection, $scope, $clock->now());
        $management = new WebhookManagement(
            $connection,
            new GuardedTenantContextRestorer($contexts),
            $this->createMock(WebhookSubscriptionStore::class),
            new SignedCursorCodec($clock, $this->keyring()),
        );

        $attempts = $management->attempts($scope, 'delivery-redacted');

        self::assertSame([
            'attempt', 'duration_ms', 'error_code', 'finished_at', 'http_status', 'outcome', 'started_at',
        ], array_keys($attempts[0]));
        $wire = JsonCanonical::encode($attempts);

        foreach (['payload-secret', 'whsec_', 'webhook-signature', 'token=private', 'worker-redacted'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $wire);
        }
    }

    private function seedFixture(Connection $connection, DataScope $scope, DateTimeImmutable $now): void
    {
        $connection->table('sys_api_webhook_subscriptions')->insert([
            'id' => 'subscription-redacted', 'revision' => 0, 'major' => 'v1', 'topic' => 'orders.placed',
            'topic_version' => 1, 'endpoint_url' => 'https://hooks.example/receiver?token=private', 'status' => 'active',
            'max_pii_class' => 'personal', 'key_refs' => '["whsec_ref"]', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $body = JsonCanonical::encode(['secret' => 'payload-secret']);
        $connection->table('sys_api_webhook_deliveries')->insert([
            'id' => 'delivery-redacted', 'subscription_id' => 'subscription-redacted', 'subscription_revision' => 0,
            'event_id' => 'event-redacted', 'topic' => 'orders.placed', 'topic_version' => 1, 'major' => 'v1',
            'endpoint_snapshot' => 'https://hooks.example/receiver?token=private',
            'scope' => JsonCanonical::encode(['account' => $scope->accountId, 'workspace' => null]),
            'body' => $body, 'body_hash' => hash('sha256', $body), 'status' => 'dead', 'attempt' => 1,
            'replay_count' => 0, 'lease_owner' => null, 'lease_until' => null, 'next_attempt_at' => $now,
            'created_at' => $now, 'finished_at' => $now, 'expires_at' => $now->add(new DateInterval('P7D')),
            'last_error' => 'http_permanent',
        ]);
        $connection->table('sys_api_webhook_attempts')->insert([
            'id' => 'attempt-redacted', 'delivery_id' => 'delivery-redacted', 'attempt' => 1,
            'lease_owner' => 'worker-redacted', 'started_at' => $now, 'finished_at' => $now,
            'outcome' => 'dead', 'http_status' => 422, 'duration_ms' => 9, 'error_code' => 'http_permanent',
        ]);
    }

    private function keyring(): CursorKeyring
    {
        return new class implements CursorKeyring
        {
            public function currentKeyId(): string
            {
                return 'current';
            }

            public function ttlSeconds(): int
            {
                return 900;
            }

            public function verifyingSecret(string $keyId): ?string
            {
                return $keyId === 'current' ? str_repeat('x', 32) : null;
            }
        };
    }

    private function configure(string $name): void
    {
        $this->app['config']->set('database.connections.'.$name, [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        DB::purge($name);
    }

    private function selectSchema(): void
    {
        $this->app->forgetInstance(ApiSchemaRegistrar::class);
        $this->app['config']->set('corex-operations.schema', [
            'budget' => ['connection' => 'api_budget_stub', 'driver' => 'sqlite', 'database' => ':memory:'],
            'tenant' => ['connection' => 'api_tenant', 'driver' => 'sqlite', 'database' => ':memory:'],
        ]);
    }
}
