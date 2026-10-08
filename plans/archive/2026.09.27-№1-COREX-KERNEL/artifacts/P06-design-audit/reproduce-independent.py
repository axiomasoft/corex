from pathlib import Path
import subprocess
import json

root = Path.cwd()
art = root / 'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P06-design-audit'

def php(name, source):
    path = art / (name + '.php')
    path.write_text('<?php\ndeclare(strict_types=1);\n' + source)
    run = subprocess.run(['/usr/bin/php8.4', str(path)], cwd=root, text=True, capture_output=True)
    record = {'command': ['/usr/bin/php8.4', str(path.relative_to(root))], 'exit_code': run.returncode, 'stdout': run.stdout, 'stderr': run.stderr}
    (art / (name + '.json')).write_text(json.dumps(record, indent=2) + '\n')
    print(json.dumps({'case': name, **record}, ensure_ascii=False))

php('tool-exception-code', r'''
enum ToolsErrorCode: string { case SchemaLimit = 'tools.schema_limit'; }
final class ToolSchemaFailure extends RuntimeException
{
    public readonly ToolsErrorCode $code;
}
''')

php('http-415-registry', r'''
require getcwd().'/vendor/autoload.php';
use CoreX\Errors\Contracts\ErrorCode;
use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Internal\CompiledProblemRegistry;
use CoreX\Errors\Internal\ProblemContribution;
use CoreX\Operations\Errors\Exceptions\ApiError;

enum PlannedOperationsCode: string implements ErrorCode {
    case UnsupportedMediaType = 'operations.unsupported_media_type';
    public function value(): string { return $this->value; }
}
$definition = new ProblemDefinition(PlannedOperationsCode::UnsupportedMediaType, 'urn:corex:problem:operations.unsupported_media_type', 415, 'Unsupported media type.', false, ErrorCategory::Unsupported, []);
$provider = new class($definition) implements ProblemDefinitionProvider {
    public function __construct(private ProblemDefinition $definition) {}
    public function definitions(): iterable { yield $this->definition; }
};
try {
    new CompiledProblemRegistry([new ProblemContribution('operations', $provider)]);
    echo "registry: accepted\n";
} catch (Throwable $error) {
    echo 'registry: '.get_class($error).': '.$error->getMessage()."\n";
}
$error = new ApiError(PlannedOperationsCode::UnsupportedMediaType, 415, 'A JSON request body is required.');
echo 'planned definition category: '.$definition->category->value."\n";
echo 'planned occurrence category: '.$error->errorDescriptor()->category->value."\n";
''')

php('webhook-lease-budget', r'''
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
    $query->shouldReceive('update')->andReturnUsing(function (array $values) use (&$writes): int { $writes[] = $values; return 1; });
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
''')

php('external-dataschema', r'''
require getcwd().'/vendor/autoload.php';
use Opis\JsonSchema\Validator;
$schema = json_decode('{"type":"object","required":["id","secret"],"properties":{"id":{"type":"string"},"secret":{"type":"string"}},"additionalProperties":false}', false, 512, JSON_THROW_ON_ERROR);
$internalData = (object) ['id' => 'order-1', 'secret' => 'hidden'];
$externalData = (object) ['id' => 'order-1'];
$validator = new Validator();
$schemaUri = 'https://schemas.example.test/orders.placed/v1.json';
echo json_encode([
    'scenario' => 'D90 permits a MessageContract dataSchema for payload; existing ExportProjector removes secret',
    'internal_dataschema' => $schemaUri,
    'external_dataschema_by_D90' => $schemaUri,
    'internal_data_valid' => $validator->validate($internalData, $schema)->isValid(),
    'external_data_valid' => $validator->validate($externalData, $schema)->isValid(),
], JSON_THROW_ON_ERROR)."\n";
''')
