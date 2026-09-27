#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Intra-major API compatibility gate (P13.11, D73).
 *
 * Compares parsed canonical snapshot structures. It never updates a baseline
 * and never line-diffs JSON. Diagnostics name the rule, the operation or
 * error, and a JSON pointer.
 */
const EXIT_OK = 0;
const EXIT_VIOLATION = 1;
const EXIT_CONFIG = 2;

const SNAPSHOT_FILES = ['errors.json', 'openapi.json', 'operations.json', 'manifest.json', 'webhooks.json'];
const REQUIRED_SNAPSHOT_FILES = ['errors.json', 'openapi.json', 'operations.json', 'manifest.json'];
const OPERATION_KEYS = [
    'ability', 'capabilities', 'contract_hash', 'cost', 'deprecation', 'effect_mode',
    'execution_supported', 'id', 'idempotency', 'method', 'mode', 'module',
    'pagination', 'path', 'read_fields', 'request_schema', 'required_scopes',
    'response_schema', 'success_status', 'version', 'write_fields',
];
const COST_KEYS = ['enforcement', 'recipe'];
const ENFORCEMENT_KEYS = ['account', 'actor', 'client', 'global'];
const RECIPE_KEYS = [
    'attribute_filter', 'base', 'field', 'highlight', 'max_units', 'relation', 'row', 'side_effect',
];
const WEBHOOK_KEYS = [
    'bulk', 'delivery', 'payload_limit_bytes', 'pii', 'replay', 'retry', 'rotation', 'schema', 'topics',
];
const TOPIC_KEYS = ['major', 'name', 'payload', 'pii_class', 'version'];
const PII_CLASSES = ['none', 'pseudonymous', 'personal', 'restricted', 'secret'];
const PII_RANK = ['none' => 0, 'pseudonymous' => 1, 'personal' => 2, 'restricted' => 3, 'secret' => 4];
const ROTATION_KEYS = [
    'algorithm', 'content', 'headers', 'id_semantics', 'max_active_keys', 'prefix',
    'secret_bytes', 'signature', 'timestamp_window_seconds',
];
const ERROR_KEYS = ['category', 'code', 'detail_keys', 'owner', 'path', 'retryable'];
const SCHEMA_KEYS = [
    'type', 'properties', 'required', 'items', 'enum', 'nullable',
    'additionalProperties', 'format', 'description', '$ref', 'oneOf', 'anyOf', 'allOf',
];
const PAGINATION_KEYS = ['ordering', 'direction', 'fields', 'types', 'unique', 'envelope', 'cursor'];
const DEPRECATION_KEYS = ['id', 'deprecatedAt', 'sunsetAt', 'successor', 'migrationUri'];
const OPENAPI_KEYS = ['info', 'openapi', 'paths', 'servers'];
const MANIFEST_KEYS = ['contract_fingerprint', 'files', 'major', 'registry_fingerprint', 'schema'];

$repoRoot = dirname(__DIR__);
$options = parseArguments($argv, $repoRoot);

if ($options['error'] !== null) {
    fwrite(STDERR, $options['error']."\n");
    exit(EXIT_CONFIG);
}

try {
    $baseline = loadSnapshot($options['baseline']);
    $candidate = loadSnapshot($options['candidate']);
} catch (RuntimeException $exception) {
    fwrite(STDOUT, $exception->getMessage()."\n");
    exit(EXIT_VIOLATION);
}

$violations = compareSnapshots($baseline, $candidate);
sort($violations);

foreach ($violations as $violation) {
    fwrite(STDOUT, $violation."\n");
}

if ($violations !== []) {
    fwrite(STDOUT, "api-contract: FAIL\n");
    exit(EXIT_VIOLATION);
}

fwrite(STDOUT, "api-contract: OK\n");
exit(EXIT_OK);

/**
 * @param  list<string>  $argv
 * @return array{baseline: string, candidate: string, error: ?string}
 */
function parseArguments(array $argv, string $repoRoot): array
{
    $baseline = $repoRoot.'/api/v1';
    $candidate = $repoRoot.'/api/v1';
    $index = 1;

    while ($index < count($argv)) {
        $token = $argv[$index];

        if (in_array($token, ['--update', '--accept', '--write'], true)) {
            return [
                'baseline' => $baseline,
                'candidate' => $candidate,
                'error' => 'api-contract: baseline update is not a check action',
            ];
        }

        if (! in_array($token, ['--baseline', '--candidate'], true)) {
            return [
                'baseline' => $baseline,
                'candidate' => $candidate,
                'error' => 'api-contract: unknown argument '.$token,
            ];
        }

        $value = $argv[$index + 1] ?? null;

        if (! is_string($value) || $value === '' || str_starts_with($value, '--')) {
            return [
                'baseline' => $baseline,
                'candidate' => $candidate,
                'error' => 'api-contract: '.$token.' requires a directory',
            ];
        }

        if ($token === '--baseline') {
            $baseline = $value;
        } else {
            $candidate = $value;
        }

        $index += 2;
    }

    return ['baseline' => $baseline, 'candidate' => $candidate, 'error' => null];
}

/**
 * @return array{files: array<string, mixed>, directory: string}
 */
function loadSnapshot(string $directory): array
{
    if (! is_dir($directory)) {
        throw new RuntimeException('unknown-schema '.$directory.' /');
    }

    $entries = array_values(array_filter(
        scandir($directory) ?: [],
        static fn (string $name): bool => $name !== '.' && $name !== '..',
    ));
    sort($entries);

    foreach ($entries as $name) {
        if (! in_array($name, SNAPSHOT_FILES, true)) {
            throw new RuntimeException('unknown-schema '.$name.' /'.$name);
        }
    }

    $files = [];

    foreach (REQUIRED_SNAPSHOT_FILES as $name) {
        $files[$name] = decodeSnapshotFile($directory, $name);
    }

    $webhookPath = rtrim($directory, '/').'/webhooks.json';
    $files['webhooks.json'] = is_file($webhookPath) ? decodeSnapshotFile($directory, 'webhooks.json') : null;

    if (is_array($files['webhooks.json'])) {
        assertWebhook($files['webhooks.json']);
    }

    assertKnownDocument($files['operations.json'], 'operations.json', '/');
    assertKnownDocument($files['errors.json'], 'errors.json', '/');
    assertKnownKeys(is_array($files['openapi.json']) ? $files['openapi.json'] : [], OPENAPI_KEYS, 'openapi.json', '/');
    assertKnownKeys(is_array($files['manifest.json']) ? $files['manifest.json'] : [], MANIFEST_KEYS, 'manifest.json', '/');

    if (! is_array($files['manifest.json']) || ($files['manifest.json']['schema'] ?? null) !== 'corex-api-snapshot/1') {
        throw new RuntimeException('unknown-schema manifest.json /schema');
    }

    return ['files' => $files, 'directory' => $directory];
}

function assertKnownDocument(mixed $document, string $subject, string $pointer): void
{
    if (! is_array($document) || ! array_is_list($document)) {
        throw new RuntimeException('unknown-schema '.$subject.' '.$pointer);
    }

    foreach ($document as $index => $row) {
        if (! is_array($row)) {
            throw new RuntimeException('unknown-schema '.$subject.' '.$pointer.$index);
        }

        $keys = $subject === 'operations.json' ? OPERATION_KEYS : ERROR_KEYS;
        assertKnownKeys($row, $keys, $subject, $pointer.$index);

        if ($subject === 'operations.json') {
            assertOperationShape($row, $subject, $pointer.$index);
        }
    }
}

/**
 * @param  array<mixed>  $row
 * @param  list<string>  $allowed
 */
function assertKnownKeys(array $row, array $allowed, string $subject, string $pointer): void
{
    foreach (array_keys($row) as $key) {
        if (! is_string($key) || ! in_array($key, $allowed, true)) {
            $segment = is_string($key) ? escapePointer($key) : 'unknown';

            throw new RuntimeException('unknown-schema '.$subject.' '.$pointer.'/'.$segment);
        }
    }
}

/**
 * @param  array<mixed>  $operation
 */
function assertOperationShape(array $operation, string $subject, string $pointer): void
{
    foreach (['request_schema', 'response_schema'] as $field) {
        if (! is_array($operation[$field] ?? null)) {
            throw new RuntimeException('unknown-schema '.$subject.' '.$pointer.'/'.$field);
        }

        assertSchema($operation[$field], $subject, $pointer.'/'.$field);
    }

    $pagination = $operation['pagination'] ?? null;

    if ($pagination !== null) {
        if (! is_array($pagination)) {
            throw new RuntimeException('unknown-schema '.$subject.' '.$pointer.'/pagination');
        }

        assertKnownKeys($pagination, PAGINATION_KEYS, $subject, $pointer.'/pagination');
    }

    $deprecation = $operation['deprecation'] ?? null;

    if ($deprecation !== null) {
        if (! is_array($deprecation)) {
            throw new RuntimeException('unknown-schema '.$subject.' '.$pointer.'/deprecation');
        }

        assertKnownKeys($deprecation, DEPRECATION_KEYS, $subject, $pointer.'/deprecation');
    }

    if (array_key_exists('cost', $operation)) {
        assertCost($operation['cost'], $subject, $pointer.'/cost');
    }
}

/**
 * @param  array<mixed>  $schema
 */
function assertSchema(array $schema, string $subject, string $pointer): void
{
    assertKnownKeys($schema, SCHEMA_KEYS, $subject, $pointer);

    foreach (($schema['properties'] ?? []) as $name => $property) {
        if (! is_string($name) || ! is_array($property)) {
            throw new RuntimeException('unknown-schema '.$subject.' '.$pointer.'/properties');
        }

        assertSchema($property, $subject, $pointer.'/properties/'.escapePointer($name));
    }

    if (isset($schema['items'])) {
        if (! is_array($schema['items'])) {
            throw new RuntimeException('unknown-schema '.$subject.' '.$pointer.'/items');
        }

        assertSchema($schema['items'], $subject, $pointer.'/items');
    }
}

/**
 * @param  array{files: array<string, mixed>}  $baseline
 * @param  array{files: array<string, mixed>}  $candidate
 * @return list<string>
 */
function compareSnapshots(array $baseline, array $candidate): array
{
    $violations = [];
    $baseMajor = $baseline['files']['manifest.json']['major'] ?? null;
    $nextMajor = $candidate['files']['manifest.json']['major'] ?? null;

    if ($baseMajor !== $nextMajor) {
        $violations[] = 'unknown-schema manifest.json /major';
    }

    $baseOperations = indexOperations($baseline['files']['operations.json']);
    $nextOperations = indexOperations($candidate['files']['operations.json']);

    foreach ($baseOperations as $id => $operation) {
        $pointer = '/operations/'.escapePointer($id);

        if (! isset($nextOperations[$id])) {
            $violations[] = 'operation-removal '.$id.' '.$pointer;

            continue;
        }

        $next = $nextOperations[$id];

        if ($operation['method'] !== $next['method'] || $operation['path'] !== $next['path']) {
            $field = $operation['method'] !== $next['method'] ? 'method' : 'path';
            $violations[] = 'operation-removal '.$id.' '.$pointer.'/'.$field;
        }

        $violations = array_merge(
            $violations,
            compareSchema($id, $operation['request_schema'], $next['request_schema'], $pointer.'/request_schema', true),
            compareSchema($id, $operation['response_schema'], $next['response_schema'], $pointer.'/response_schema', false),
            comparePagination($id, $operation['pagination'] ?? null, $next['pagination'] ?? null, $pointer.'/pagination'),
            compareCost($id, $operation['cost'] ?? null, $next['cost'] ?? null, $pointer.'/cost/enforcement'),
        );
    }

    $baseErrors = indexErrors($baseline['files']['errors.json']);
    $nextErrors = indexErrors($candidate['files']['errors.json']);

    foreach ($baseErrors as $key => $error) {
        $pointer = '/errors/'.escapePointer((string) $error['owner']).'/'.escapePointer((string) $error['code']);

        if (! isset($nextErrors[$key])) {
            $violations[] = 'error-contract-drift '.$error['code'].' '.$pointer;

            continue;
        }

        $next = $nextErrors[$key];

        foreach (['category', 'path', 'retryable'] as $field) {
            if ($error[$field] !== $next[$field]) {
                $violations[] = 'error-contract-drift '.$error['code'].' '.$pointer.'/'.$field;
            }
        }

        $removedKeys = array_diff($error['detail_keys'], $next['detail_keys']);

        if ($removedKeys !== []) {
            $violations[] = 'error-contract-drift '.$error['code'].' '.$pointer.'/detail_keys';
        }
    }

    return array_merge($violations, compareWebhooks(
        $baseline['files']['webhooks.json'] ?? null,
        $candidate['files']['webhooks.json'] ?? null,
    ));
}

/**
 * @param  list<array<string, mixed>>  $operations
 * @return array<string, array<string, mixed>>
 */
function indexOperations(array $operations): array
{
    $indexed = [];

    foreach ($operations as $operation) {
        $indexed[(string) $operation['id']] = $operation;
    }

    return $indexed;
}

/**
 * @param  list<array<string, mixed>>  $errors
 * @return array<string, array<string, mixed>>
 */
function indexErrors(array $errors): array
{
    $indexed = [];

    foreach ($errors as $error) {
        $indexed[$error['owner']."\0".$error['code']] = $error;
    }

    return $indexed;
}

/**
 * @param  array<string, mixed>  $baseline
 * @param  array<string, mixed>  $candidate
 * @return list<string>
 */
function compareSchema(string $subject, array $baseline, array $candidate, string $pointer, bool $input): array
{
    $violations = [];

    if (array_key_exists('type', $baseline) && ($baseline['type'] ?? null) !== ($candidate['type'] ?? null)) {
        $violations[] = 'type-change '.$subject.' '.$pointer.'/type';
    }

    $baseNullable = ($baseline['nullable'] ?? false) === true;
    $nextNullable = ($candidate['nullable'] ?? false) === true;

    if ($baseNullable !== $nextNullable) {
        $violations[] = 'nullability-change '.$subject.' '.$pointer.'/nullable';
    }

    if (isset($baseline['enum'])) {
        $removed = array_diff($baseline['enum'], $candidate['enum'] ?? []);

        if ($removed !== []) {
            $violations[] = 'enum-removal '.$subject.' '.$pointer.'/enum';
        }
    }

    $baseRequired = $baseline['required'] ?? [];
    $nextRequired = $candidate['required'] ?? [];

    if ($input && array_diff($nextRequired, $baseRequired) !== []) {
        $violations[] = 'required-input-addition '.$subject.' '.$pointer.'/required';
    }

    foreach (($baseline['properties'] ?? []) as $name => $property) {
        $child = $pointer.'/properties/'.escapePointer((string) $name);

        if (! isset($candidate['properties'][$name])) {
            if (! $input) {
                $violations[] = 'response-field-removal '.$subject.' '.$child;
            }

            continue;
        }

        $violations = array_merge(
            $violations,
            compareSchema($subject, $property, $candidate['properties'][$name], $child, $input),
        );
    }

    if (isset($baseline['items'], $candidate['items']) && is_array($baseline['items']) && is_array($candidate['items'])) {
        $violations = array_merge(
            $violations,
            compareSchema($subject, $baseline['items'], $candidate['items'], $pointer.'/items', $input),
        );
    }

    return $violations;
}

/**
 * @return list<string>
 */
function comparePagination(string $subject, mixed $baseline, mixed $candidate, string $pointer): array
{
    if ($baseline === $candidate) {
        return [];
    }

    if (! is_array($baseline) || ! is_array($candidate)) {
        return ['pagination-reinterpretation '.$subject.' '.$pointer];
    }

    $violations = [];

    foreach (PAGINATION_KEYS as $key) {
        if (! array_key_exists($key, $baseline) && ! array_key_exists($key, $candidate)) {
            continue;
        }

        if (($baseline[$key] ?? null) !== ($candidate[$key] ?? null)) {
            $violations[] = 'pagination-reinterpretation '.$subject.' '.$pointer.'/'.$key;
        }
    }

    return $violations;
}

function escapePointer(string $segment): string
{
    return str_replace(['~', '/'], ['~0', '~1'], $segment);
}

function decodeSnapshotFile(string $directory, string $name): mixed
{
    $path = rtrim($directory, '/').'/'.$name;
    $decoded = json_decode((string) file_get_contents($path), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('unknown-schema '.$name.' /'.$name);
    }

    return $decoded;
}

function assertCost(mixed $cost, string $subject, string $pointer): void
{
    if (! is_array($cost)) {
        throw new RuntimeException('unknown-schema '.$subject.' '.$pointer);
    }

    assertKnownKeys($cost, COST_KEYS, $subject, $pointer);
    $enforcement = $cost['enforcement'] ?? null;
    $recipe = $cost['recipe'] ?? null;

    if (! is_array($enforcement) || ! is_array($recipe)) {
        throw new RuntimeException('unknown-schema '.$subject.' '.$pointer);
    }

    assertKnownKeys($enforcement, ENFORCEMENT_KEYS, $subject, $pointer.'/enforcement');
    assertKnownKeys($recipe, RECIPE_KEYS, $subject, $pointer.'/recipe');

    foreach (ENFORCEMENT_KEYS as $level) {
        if (! in_array($enforcement[$level] ?? null, ['enforce', 'observe'], true)) {
            throw new RuntimeException('unknown-schema '.$subject.' '.$pointer.'/enforcement/'.$level);
        }
    }
}

/** @param  array<string, mixed>  $document */
function assertWebhook(array $document): void
{
    assertKnownKeys($document, WEBHOOK_KEYS, 'webhooks.json', '');

    if (($document['schema'] ?? null) !== 'corex-webhook-contract/1' || ! is_array($document['topics'] ?? null) || ! array_is_list($document['topics'])) {
        throw new RuntimeException('unknown-schema webhooks.json /schema');
    }

    foreach ($document['topics'] as $index => $topic) {
        if (! is_array($topic)) {
            throw new RuntimeException('unknown-schema webhooks.json /topics/'.$index);
        }

        assertKnownKeys($topic, TOPIC_KEYS, 'webhooks.json', '/topics/'.$index);

        if (! in_array($topic['pii_class'] ?? null, PII_CLASSES, true) || ! is_array($topic['payload'] ?? null)) {
            throw new RuntimeException('unknown-schema webhooks.json /topics/'.$index);
        }

        assertSchema($topic['payload'], 'webhooks.json', '/topics/'.$index.'/payload');
    }

    $rotation = $document['rotation'] ?? null;

    if (! is_array($rotation)) {
        throw new RuntimeException('unknown-schema webhooks.json /rotation');
    }

    assertKnownKeys($rotation, ROTATION_KEYS, 'webhooks.json', '/rotation');
}

/** @return list<string> */
function compareCost(string $subject, mixed $baseline, mixed $candidate, string $pointer): array
{
    if ($baseline === null) {
        return [];
    }

    $base = is_array($baseline) ? ($baseline['enforcement'] ?? null) : null;
    $next = is_array($candidate) ? ($candidate['enforcement'] ?? null) : null;

    if ($base !== $next) {
        return ['cost-default-enforcement '.$subject.' '.$pointer];
    }

    return [];
}

/** @return list<string> */
function compareWebhooks(mixed $baseline, mixed $candidate): array
{
    if ($baseline === null) {
        return [];
    }

    if (! is_array($baseline) || ! is_array($candidate)) {
        return ['signature-semantics webhooks.json /webhooks'];
    }

    $violations = [];
    $baseTopics = indexTopics($baseline['topics'] ?? []);
    $nextTopics = indexTopics($candidate['topics'] ?? []);

    foreach ($baseTopics as $id => $topic) {
        $pointer = '/topics/'.escapePointer($id);

        if (! isset($nextTopics[$id])) {
            $violations[] = 'topic-removal '.$id.' '.$pointer;

            continue;
        }

        $next = $nextTopics[$id];

        if (($topic['major'] ?? null) !== ($next['major'] ?? null)) {
            $violations[] = 'major-rebind '.$id.' '.$pointer.'/major';
        }

        $baseRank = PII_RANK[$topic['pii_class']] ?? -1;
        $nextRank = PII_RANK[$next['pii_class']] ?? -1;

        if ($nextRank > $baseRank) {
            $violations[] = 'pii-widening '.$id.' '.$pointer.'/pii_class';
        }

        $violations = array_merge($violations, array_values(array_unique(array_merge(
            compareSchema($id, $topic['payload'], $next['payload'], $pointer.'/payload', true),
            compareSchema($id, $topic['payload'], $next['payload'], $pointer.'/payload', false),
        ))));
    }

    foreach (ROTATION_KEYS as $key) {
        if (($baseline['rotation'][$key] ?? null) === ($candidate['rotation'][$key] ?? null)) {
            continue;
        }

        $rule = $key === 'id_semantics' ? 'id-semantics' : 'signature-semantics';
        $violations[] = $rule.' webhooks.json /rotation/'.$key;
    }

    foreach (['delivery', 'payload_limit_bytes', 'replay', 'retry'] as $key) {
        if (($baseline[$key] ?? null) !== ($candidate[$key] ?? null)) {
            $violations[] = 'signature-semantics webhooks.json /'.$key;
        }
    }

    return $violations;
}

/**
 * @param  list<array<string, mixed>>  $topics
 * @return array<string, array<string, mixed>>
 */
function indexTopics(array $topics): array
{
    $indexed = [];

    foreach ($topics as $topic) {
        $indexed[$topic['name'].'.v'.$topic['version']] = $topic;
    }

    return $indexed;
}
