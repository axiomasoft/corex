<?php

declare(strict_types=1);

namespace CoreX\OfflineMigration\Fixture;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Internal\ArrayDataScopeCodec;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Messaging\Data\MessagePayload;
use CoreX\Messaging\Enums\MessageClass;
use CoreX\Messaging\Enums\PiiClass;
use CoreX\Messaging\Internal\JsonCloudEventCodec;
use CoreX\Messaging\Testing\MessagingFixtures;
use CoreX\OfflineMigration\SourceSnapshot;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Time\Testing\FrozenClock;
use CoreX\Webhooks\Contracts\WebhookEgressPolicy;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\WebhookDestination;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Data\WebhookOptions;
use CoreX\Webhooks\Data\WebhookSubscription;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Internal\SqlWebhookDeliveryStore;
use CoreX\Webhooks\Internal\SqlWebhookSubscriptionStore;
use CoreX\Webhooks\Internal\WebhookFanout;
use CoreX\Webhooks\Internal\WebhookRetryPolicy;
use CoreX\Webhooks\Internal\WebhookTopicCatalogue;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Packman\Schema\Laravel\PackmanMigration;
use RuntimeException;
use SQLite3;

use function CoreX\OfflineMigration\canonical;
use function CoreX\OfflineMigration\check;
use function CoreX\OfflineMigration\database;
use function CoreX\OfflineMigration\field;
use function CoreX\OfflineMigration\rows;
use function CoreX\OfflineMigration\schemaFingerprint;

require __DIR__.'/../src/Input.php';
require __DIR__.'/../src/Database.php';
require __DIR__.'/../src/SourceSnapshot.php';

check(count($argv) === 3, 'Usage: php export-source-v1.php AUDITED_BASELINE_CHECKOUT FRESH_OUTPUT_DIR');
$baseline = realpath($argv[1]);
check($baseline !== false && is_file($baseline.'/vendor/autoload.php'), 'Install the audited baseline dependencies first.');
$git = static function (string $command) use ($baseline): string {
    exec('git -C '.escapeshellarg($baseline).' '.$command, $lines, $exit);
    check($exit === 0, "Cannot verify the audited baseline checkout with git {$command}.");

    return implode("\n", $lines);
};
$gitHead = $git('rev-parse HEAD');
check($gitHead === SourceSnapshot::Artifact, 'The baseline checkout must be at the audited 1.x commit.');
check($git('status --porcelain --untracked-files=no') === '',
    'The baseline checkout must have no tracked changes.');
require $baseline.'/vendor/autoload.php';

$output = $argv[2];
check(! file_exists($output) && mkdir($output, 0700), 'The export requires a fresh output directory.');
$path = $output.'/source-v1.sqlite';
check(touch($path), 'Cannot create standalone export.');

$container = new Container;
Container::setInstance($container);
$capsule = new Manager($container);
$capsule->addConnection(['driver' => 'sqlite', 'database' => $path, 'prefix' => '']);
$container->instance(DatabaseManager::class, $capsule->getDatabaseManager());
$connection = $capsule->getConnection();
$migrations = glob($baseline.'/packages/webhooks/database/migrations/tenant/*.php');
check($migrations !== false && $migrations !== [], 'The baseline webhook migrations are missing.');
foreach ($migrations as $pathToMigration) {
    $migration = require $pathToMigration;
    check($migration instanceof PackmanMigration && is_callable([$migration, 'up']), "Invalid baseline migration [{$pathToMigration}].");
    $migration->up();
}

$connection->getSchemaBuilder()->create('sys_inbox_messages', function (Blueprint $table): void {
    $table->string('consumer', 120);
    $table->string('message_id', 64);
    $table->string('name', 160);
    $table->timestampTz('processed_at', 6);
    $table->primary(['consumer', 'message_id']);
});

$clock = new FrozenClock(new DateTimeImmutable('2026-10-01T12:00:00Z'));
$projector = new class implements WebhookPayloadProjector
{
    public function project(MessageEnvelope $message, WebhookTopic $topic): array
    {
        return match ($message->messageId) {
            'event-pending' => ['value' => null],
            'event-succeeded' => ['value' => []],
            'event-dead' => ['value' => str_repeat('x', WebhookFanout::BodyLimit)],
            default => throw new RuntimeException("Unexpected fixture event [{$message->messageId}]."),
        };
    }
};
$topicName = 'equipment.changed';
$schema = ['value' => 'string'];
$topic = new WebhookTopic($topicName, 1, 'v1', $schema, hash('sha256', JsonCanonical::encode($schema)), 'none', $topicName);
$contracts = MessagingFixtures::contracts([
    new MessageContract($topicName, 1, MessageClass::IntegrationEvent, 'equipment/registry', PiiClass::None, 'webhooks'),
]);
$catalogue = new WebhookTopicCatalogue($contracts, [$topic], [$topicName => $projector]);
$fanout = new WebhookFanout($connection, MessagingFixtures::inbox($connection, $clock), $catalogue,
    [$topicName => $projector], $clock, new JsonCloudEventCodec($contracts));
$context = new class implements TenantContextRestorer
{
    public function restore(DataScope $scope): TenantContext
    {
        return new TenantContext(new AccountRef('account-a', 'account-a', 'active', [], []), boxed: true);
    }
};
$egress = new class implements WebhookEgressPolicy
{
    public function allows(DataScope $scope, WebhookDestination $destination): bool
    {
        return true;
    }
};
$subscriptions = new SqlWebhookSubscriptionStore($connection, $context, $catalogue, $clock, $egress);
$options = new WebhookOptions(
    deliveryEnabled: true,
    workerReady: true,
    leaseSeconds: 30,
    bodyRetentionSeconds: 604800,
    historyRetentionSeconds: 604800,
    replayHorizonSeconds: 604800,
    organizationDeniedCidrs: [],
    topics: [$topic],
    projectors: [$topicName => $projector::class],
    maxAttempts: 10,
    retryHorizonSeconds: 604800,
);
$deliveries = new SqlWebhookDeliveryStore($connection, $context, $clock, new WebhookRetryPolicy($options));

$scope = static fn (string $actor): DataScope => new DataScope(
    accountId: 'account-a',
    workspaceId: null,
    connectionIdentity: 'boxed:account-a',
    actorId: $actor,
    restoreEpoch: str_repeat('a', 64),
    boxed: true,
);
$subscribe = static fn (string $id, string $endpoint): WebhookSubscription => new WebhookSubscription(
    $id, 0, 'v1', $topicName, 1, $endpoint, 'active', 'none', ['fixture-key'], $clock->now(), $clock->now(),
);
$message = static fn (string $id, DataScope $scope): MessageEnvelope => new MessageEnvelope(
    messageId: $id,
    source: 'equipment/registry',
    name: $topicName,
    contractVersion: 1,
    occurredAt: $clock->now(),
    scope: new ArrayDataScopeCodec()->encode($scope),
    payload: new MessagePayload(['id' => $id]),
    piiClass: PiiClass::None,
);

$actorB = $scope('user-b');
$subscriptions->create($actorB, $subscribe('subscription-b', 'https://archive.example.invalid/events'), true, true);
$fanout->accept($message('event-succeeded', $actorB));
$claimed = $deliveries->claim($actorB, 'fixture-worker');
check($claimed !== null, 'The baseline owner must claim one delivery.');
$clock->advance(new DateInterval('PT1S'));
check($deliveries->finish($claimed, new WebhookHttpResult(204, null, null, 20)), 'The baseline owner must record a success receipt.');
$subscriptions->disable($actorB, 'subscription-b', 0);

$actorA = $scope('user-a');
$subscriptions->create($actorA, $subscribe('subscription-a', 'https://receiver.example.invalid/events'), true, true);
$fanout->accept($message('event-pending', $actorA));
$fanout->accept($message('event-dead', $actorA));
$connection->getSchemaBuilder()->drop('sys_inbox_messages');

$pdo = $connection->getPdo();
$db = database($path, write: false);
$ddl = [];
foreach (rows($db, "SELECT sql FROM sqlite_schema WHERE sql IS NOT NULL
    ORDER BY CASE type WHEN 'table' THEN 1 WHEN 'index' THEN 2 ELSE 3 END, name COLLATE BINARY") as $row) {
    $ddl[] = field($row, 'sql');
}
$origin = "-- Generated by tests/export-source-v1.php at {$gitHead}.\n";
check(file_put_contents($output.'/source-v1-schema.sql', $origin.implode(";\n", $ddl).";\n") !== false, 'Cannot write schema export.');

$literal = static function (int|float|string|null $value) use ($pdo): string {
    check(! is_float($value), 'The baseline fixture SQL dump cannot contain floating-point values.');

    if (is_string($value)) {
        $quoted = $pdo->quote($value);
        check($quoted !== false, 'Cannot quote a value for the SQLite export.');

        return $quoted;
    }

    return $value === null ? 'NULL' : (string) $value;
};
$inserts = [];
foreach (['subscriptions', 'deliveries', 'attempts'] as $suffix) {
    $table = 'sys_api_webhook_'.$suffix;
    foreach (rows($db, "SELECT * FROM {$table} ORDER BY id COLLATE BINARY") as $row) {
        $values = array_map($literal, $row);
        $inserts[] = "INSERT INTO {$table} VALUES (".implode(', ', $values).');';
    }
}
check(file_put_contents($output.'/source-v1-data.sql', $origin.implode("\n", $inserts)."\n") !== false, 'Cannot write data export.');
echo canonical([
    'frozen_baseline' => $gitHead,
    'laravel' => Application::VERSION,
    'sqlite' => SQLite3::version()['versionString'],
    'source_schema_sha256' => schemaFingerprint($db),
    'triggers' => $db->querySingle("SELECT count(*) FROM sqlite_schema WHERE type = 'trigger'"),
    'subscriptions' => $db->querySingle('SELECT count(*) FROM sys_api_webhook_subscriptions'),
    'deliveries' => $db->querySingle('SELECT count(*) FROM sys_api_webhook_deliveries'),
    'attempts' => $db->querySingle('SELECT count(*) FROM sys_api_webhook_attempts'),
    'owner_timestamp' => $db->querySingle('SELECT created_at FROM sys_api_webhook_deliveries ORDER BY created_at LIMIT 1'),
]).PHP_EOL;
