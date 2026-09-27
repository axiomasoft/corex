<?php

declare(strict_types=1);

namespace CoreX\Api\Internal;

use CoreX\Api\Data\ApiProfile;
use CoreX\Api\Data\WebhookTopic;
use CoreX\Api\OpenApiDocumentGenerator;
use CoreX\Api\Operation;
use CoreX\Api\OperationRegistry;
use InvalidArgumentException;

/** @internal Installed-code v1 snapshot. It does not record tenant activation. */
final class ContractSnapshotBuilder
{
    public const array FILES = ['errors.json', 'openapi.json', 'operations.json', 'manifest.json', 'webhooks.json'];

    /** @var array<string, string> */
    private const array ENFORCEMENT = [
        'account' => 'observe',
        'actor' => 'observe',
        'client' => 'observe',
        'global' => 'enforce',
    ];

    /** @var array<string, int> */
    private const array RECIPE = [
        'attribute_filter' => 0,
        'base' => 1,
        'field' => 0,
        'highlight' => 0,
        'max_units' => 10000,
        'relation' => 0,
        'row' => 0,
        'side_effect' => 0,
    ];

    /** @var list<string> */
    private const array BULK_STATES = [
        'cancelled', 'cancelling', 'completed', 'expired', 'failed', 'pending', 'running',
    ];

    /**
     * @param  list<WebhookTopic>  $topics
     */
    public function __construct(
        private readonly OperationRegistry $operations,
        private readonly OpenApiDocumentGenerator $openApi,
        private readonly ApiProfile $profile,
        private readonly array $topics = [],
    ) {}

    /**
     * @param  list<array<string, mixed>>  $errorContracts
     * @return array<string, string>
     */
    public function documents(array $errorContracts, string $major = 'v1'): array
    {
        $operations = [];

        foreach ($this->operations->all($major) as $operation) {
            $operations[] = $this->operation($operation);
        }

        $known = array_column($operations, 'id');
        $contour = [];

        foreach ($this->contourOperations($major) as $operation) {
            if (! in_array($operation['id'], $known, true)) {
                $operations[] = $operation;
                $contour[] = $operation;
            }
        }

        usort($operations, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);
        $normalized = SnapshotJson::normalize($this->openApi->document($major));

        if (! is_array($normalized)) {
            throw new InvalidArgumentException('OpenAPI snapshot is not a document.');
        }

        $openapi = $this->mergeOpenApi($normalized, $contour);
        $bodies = [
            'errors.json' => SnapshotJson::pretty($this->errors($errorContracts)),
            'openapi.json' => SnapshotJson::pretty($openapi),
            'operations.json' => SnapshotJson::pretty($operations),
            'webhooks.json' => SnapshotJson::pretty($this->webhooks($major)),
        ];
        $bodies['manifest.json'] = SnapshotJson::pretty([
            'contract_fingerprint' => $this->profile->contractFingerprint,
            'files' => [
                'errors.json' => hash('sha256', $bodies['errors.json']),
                'openapi.json' => hash('sha256', $bodies['openapi.json']),
                'operations.json' => hash('sha256', $bodies['operations.json']),
                'webhooks.json' => hash('sha256', $bodies['webhooks.json']),
            ],
            'major' => $major,
            'registry_fingerprint' => $this->profile->registryFingerprint,
            'schema' => 'corex-api-snapshot/1',
        ]);

        return $bodies;
    }

    /** @return array<string, mixed> */
    private function operation(Operation $operation): array
    {
        return [
            'ability' => $operation->ability,
            'capabilities' => $operation->capabilities,
            'contract_hash' => $operation->contractHash,
            'cost' => $this->cost($operation->cost),
            'deprecation' => $operation->deprecation,
            'effect_mode' => $operation->effectMode,
            'execution_supported' => $operation->executionSupported,
            'id' => $operation->id,
            'idempotency' => $operation->idempotency->value,
            'method' => $operation->method,
            'mode' => $operation->mode->value,
            'module' => $operation->module,
            'pagination' => $operation->pagination,
            'path' => $operation->path,
            'read_fields' => $operation->readFields,
            'request_schema' => $operation->requestSchema,
            'required_scopes' => $operation->requiredScopes,
            'response_schema' => $operation->responseSchema,
            'success_status' => $operation->successStatus,
            'version' => $operation->version,
            'write_fields' => $operation->writeFields,
        ];
    }

    /**
     * @param  array<string, int>|null  $declared
     * @return array{enforcement: array<string, string>, recipe: array<string, int>}
     */
    private function cost(?array $declared): array
    {
        $recipe = self::RECIPE;

        foreach ($declared ?? [] as $key => $value) {
            if (array_key_exists($key, $recipe)) {
                $recipe[$key] = $value;
            }
        }

        ksort($recipe);

        return ['enforcement' => self::ENFORCEMENT, 'recipe' => $recipe];
    }

    /**
     * @param  list<array<string, mixed>>  $errorContracts
     * @return list<array<string, mixed>>
     */
    private function errors(array $errorContracts): array
    {
        $errors = $errorContracts;
        $present = [];

        foreach ($errors as $row) {
            $present[($row['owner'] ?? '')."\0".($row['code'] ?? '')] = true;
        }

        foreach ($this->safeCodes() as $row) {
            if (! isset($present[$row['owner']."\0".$row['code']])) {
                $errors[] = $row;
            }
        }

        usort($errors, static fn (array $left, array $right): int => [$left['owner'] ?? '', $left['code'] ?? ''] <=> [$right['owner'] ?? '', $right['code'] ?? '']);

        return array_map(static fn (array $row): array => [
            'category' => $row['category'],
            'code' => $row['code'],
            'detail_keys' => $row['detail_keys'],
            'owner' => $row['owner'],
            'path' => $row['path'],
            'retryable' => $row['retryable'],
        ], $errors);
    }

    /** @return list<array<string, mixed>> */
    private function safeCodes(): array
    {
        return [
            $this->safeCode('bulk_not_ready', 'conflict', true),
            $this->safeCode('bulk_reconciliation_required', 'conflict', false),
            $this->safeCode('bulk_result_expired', 'not_found', false),
            $this->safeCode('webhook_endpoint_changed', 'conflict', false),
            $this->safeCode('webhook_payload_too_large', 'validation', false),
            $this->safeCode('webhook_replay_expired', 'conflict', false),
        ];
    }

    /** @return array<string, mixed> */
    private function safeCode(string $code, string $category, bool $retryable): array
    {
        return [
            'category' => $category,
            'code' => $code,
            'detail_keys' => [],
            'owner' => 'corex/operations',
            'path' => null,
            'retryable' => $retryable,
        ];
    }

    /** @return array<string, mixed> */
    private function webhooks(string $major): array
    {
        $topics = [];

        foreach ($this->topics as $topic) {
            if ($topic->major === $major) {
                $topics[] = $this->topic($topic);
            }
        }

        usort($topics, static fn (array $left, array $right): int => [$left['name'], $left['version']] <=> [$right['name'], $right['version']]);

        return [
            'bulk' => [
                'failure_page_max' => 100,
                'result_ttl_seconds' => 86400,
                'states' => self::BULK_STATES,
                'tombstone_ttl_seconds' => 2592000,
            ],
            'delivery' => ['guarantee' => 'at-least-once', 'ordering' => 'none'],
            'payload_limit_bytes' => 20 * 1024,
            'pii' => [
                'classes' => WebhookTopic::CLASSES,
                'secret_subscribable' => false,
            ],
            'replay' => [
                'body_retention_seconds' => 604800,
                'horizon_seconds' => 604800,
                'max_count' => 3,
                'range_max' => 100,
                'range_window_seconds' => 86400,
            ],
            'retry' => [
                'attempts' => 12,
                'horizon_seconds' => 259200,
                'lease_seconds' => 30,
                'maximum_delay_seconds' => 3600,
                'minimum_delay_seconds' => 1,
            ],
            'rotation' => [
                'algorithm' => 'hmac-sha256',
                'content' => 'delivery-id.timestamp.raw-body',
                'headers' => ['webhook-id', 'webhook-timestamp', 'webhook-signature'],
                'id_semantics' => 'delivery-id',
                'max_active_keys' => 2,
                'prefix' => 'whsec_',
                'secret_bytes' => 32,
                'signature' => 'v1',
                'timestamp_window_seconds' => 300,
            ],
            'schema' => 'corex-webhook-contract/1',
            'topics' => $topics,
        ];
    }

    /** @return array<string, mixed> */
    private function topic(WebhookTopic $topic): array
    {
        $properties = [];
        $required = [];

        foreach ($topic->schema as $field => $type) {
            $properties[(string) $field] = ['type' => $type];
            $required[] = (string) $field;
        }

        sort($required);

        return [
            'major' => $topic->major,
            'name' => $topic->name,
            'payload' => ['properties' => $properties, 'required' => $required, 'type' => 'object'],
            'pii_class' => $topic->piiClass,
            'version' => $topic->version,
        ];
    }

    /**
     * @param  array<mixed>  $document
     * @param  list<array<string, mixed>>  $contour
     * @return array<mixed>
     */
    private function mergeOpenApi(array $document, array $contour): array
    {
        $paths = $document['paths'] ?? [];
        $paths = is_array($paths) ? $paths : [];

        foreach ($contour as $operation) {
            $paths[$operation['path']][strtolower((string) $operation['method'])] = [
                'operationId' => $operation['id'],
                'responses' => [(string) $operation['success_status'] => ['description' => 'Success']],
                'x-corex-ability' => $operation['ability'],
                'x-corex-cost' => $operation['cost'],
                'x-corex-module' => 'corex/operations',
            ];
        }

        $document['paths'] = $paths;

        return $document;
    }

    /** @return list<array<string, mixed>> */
    private function contourOperations(string $major): array
    {
        $status = ['enum' => self::BULK_STATES, 'type' => 'string'];
        $object = ['type' => 'object'];
        $rows = [
            ['bulk-operations.cancel', 'POST', '/bulk-operations/{id}/cancel', 'async_command', 'required', 'tenant_transactional', 'bulk.manage', 202, $object, ['properties' => ['status' => $status], 'required' => ['status'], 'type' => 'object']],
            ['bulk-operations.failures', 'GET', '/bulk-operations/{id}/failures', 'query', 'forbidden', null, 'bulk.manage', 200, $object, $object],
            ['bulk-operations.result', 'GET', '/bulk-operations/{id}/result', 'query', 'forbidden', null, 'bulk.manage', 200, $object, $object],
            ['bulk-operations.show', 'GET', '/bulk-operations/{id}', 'query', 'forbidden', null, 'bulk.manage', 200, $object, ['properties' => ['status' => $status], 'required' => ['status'], 'type' => 'object']],
            ['bulk-operations.submit', 'POST', '/bulk-operations', 'async_command', 'required', 'tenant_transactional', 'bulk.manage', 202, $object, ['properties' => ['operation_id' => ['type' => 'string'], 'status' => $status], 'required' => ['operation_id', 'status'], 'type' => 'object']],
            ['webhook-deliveries.attempts', 'GET', '/webhook-deliveries/{id}/attempts', 'query', 'forbidden', null, 'webhooks.manage', 200, $object, $object],
            ['webhook-deliveries.list', 'GET', '/webhook-deliveries', 'query', 'forbidden', null, 'webhooks.manage', 200, $object, $object],
            ['webhook-deliveries.range-replay', 'POST', '/webhook-deliveries/replay', 'command', 'required', 'tenant_transactional', 'webhooks.replay', 200, $object, $object],
            ['webhook-deliveries.replay', 'POST', '/webhook-deliveries/{id}/replay', 'command', 'required', 'tenant_transactional', 'webhooks.replay', 200, $object, $object],
            ['webhook-deliveries.show', 'GET', '/webhook-deliveries/{id}', 'query', 'forbidden', null, 'webhooks.manage', 200, $object, $object],
            ['webhook-subscriptions.create', 'POST', '/webhook-subscriptions', 'command', 'required', 'tenant_transactional', 'webhooks.manage', 201, $object, $object],
            ['webhook-subscriptions.disable', 'POST', '/webhook-subscriptions/{id}/disable', 'command', 'required', 'tenant_transactional', 'webhooks.manage', 200, $object, $object],
            ['webhook-subscriptions.list', 'GET', '/webhook-subscriptions', 'query', 'forbidden', null, 'webhooks.manage', 200, $object, $object],
            ['webhook-subscriptions.update', 'PATCH', '/webhook-subscriptions/{id}', 'command', 'required', 'tenant_transactional', 'webhooks.manage', 200, $object, $object],
        ];
        $operations = [];

        foreach ($rows as $row) {
            $operations[] = $this->contour($row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7], $major, $row[8], $row[9]);
        }

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $request
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function contour(
        string $id,
        string $method,
        string $path,
        string $mode,
        string $idempotency,
        ?string $effect,
        string $ability,
        int $status,
        string $major,
        array $request,
        array $response,
    ): array {
        $operation = [
            'ability' => $ability,
            'capabilities' => [],
            'cost' => $this->cost(null),
            'deprecation' => null,
            'effect_mode' => $effect,
            'execution_supported' => true,
            'id' => $id,
            'idempotency' => $idempotency,
            'method' => $method,
            'mode' => $mode,
            'module' => 'corex/operations',
            'pagination' => null,
            'path' => $path,
            'read_fields' => null,
            'request_schema' => $request,
            'required_scopes' => [$ability],
            'response_schema' => $response,
            'success_status' => $status,
            'version' => $major,
            'write_fields' => null,
        ];
        $operation['contract_hash'] = hash('sha256', JsonCanonical::encode([
            'operation' => $operation,
            'schema' => 'load-contour-operation/1',
        ]));

        return $operation;
    }
}
