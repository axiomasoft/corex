<?php

declare(strict_types=1);

require getcwd().'/vendor/autoload.php';
use CoreX\Webhooks\Internal\SqlWebhookDeliveryStore;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

foreach ([['maxAttempts' => 3, 'horizon' => 3600, 'attempt' => 3, 'age' => 120], ['maxAttempts' => 20, 'horizon' => 3600, 'attempt' => 1, 'age' => 7200]] as $scenario) {
    $created = new DateTimeImmutable('2026-10-03T00:00:00Z');
    $now = $created->modify('+'.$scenario['age'].' seconds');
    $row = (object) [
        'id' => 'delivery-1', 'subscription_id' => 'sub-1', 'subscription_revision' => 0,
        'event_id' => 'event-1', 'topic' => 'test.event', 'topic_version' => 1, 'major' => 'v1',
        'endpoint_snapshot' => 'https://hooks.example/receiver', 'scope' => '{"accountId":"a-1"}',
        'body' => '{}', 'body_hash' => hash('sha256', '{}'), 'status' => 'sending',
        'attempt' => $scenario['attempt'], 'replay_count' => 0, 'lease_owner' => 'worker-1',
        'lease_until' => $now->modify('-1 second'), 'next_attempt_at' => $created,
        'created_at' => $created, 'finished_at' => null, 'expires_at' => $created->modify('+7 days'), 'last_error' => null,
    ];
    $writes = [];
    $query = Mockery::mock(Builder::class);
    $query->shouldReceive('where', 'orderBy', 'lockForUpdate')->andReturnSelf();
    $query->shouldReceive('get')->andReturn(new Collection([$row]));
    $query->shouldReceive('update')->andReturnUsing(function (array $values) use (&$writes): int {
        $writes[] = $values;

        return 1;
    });
    $connection = Mockery::mock(Connection::class);
    $connection->shouldReceive('table')->andReturn($query);
    $class = new ReflectionClass(SqlWebhookDeliveryStore::class);
    $store = $class->newInstanceWithoutConstructor();
    $class->getProperty('connection')->setValue($store, $connection);
    $class->getMethod('recoverExpired')->invoke($store, $now);
    $deliveryWrite = $writes[1];
    echo json_encode(['configured_contract' => $scenario, 'required_status' => 'dead', 'actual_status' => $deliveryWrite['status'], 'actual_last_error' => $deliveryWrite['last_error'], 'database_connection_opened' => false], JSON_THROW_ON_ERROR)."\n";
    Mockery::close();
}
