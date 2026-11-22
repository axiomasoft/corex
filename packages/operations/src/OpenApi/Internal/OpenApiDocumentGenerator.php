<?php

declare(strict_types=1);

namespace CoreX\Operations\OpenApi\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Contracts\SchemaContributor;
use CoreX\Operations\Idempotency\Enums\IdempotencyRequirement;
use CoreX\Operations\OpenApi\Contracts\OpenApiDocuments;
use InvalidArgumentException;
use Packman\Attributes\Internal;

/** Build-time installed-code projection; not a public endpoint or a grant of access. */
#[Internal]
final readonly class OpenApiDocumentGenerator implements OpenApiDocuments
{
    public function __construct(
        private OperationRegistry $operations,
        private SchemaContributor $schemas,
    ) {}

    /**
     * $serverUrl includes the application's chosen mount prefix and API version.
     *
     * @return array<string, mixed>
     */
    public function document(string $version, string $serverUrl = ''): array
    {
        $paths = [];
        $meta = ['type' => 'object', 'required' => ['request_id', 'correlation_id', 'api_version'], 'properties' => [
            'request_id' => ['type' => 'string'],
            'correlation_id' => ['type' => 'string'],
            'api_version' => ['type' => 'string', 'const' => $version],
        ]];
        $extensions = [
            'code' => ['type' => 'string'],
            'category' => ['type' => 'string'],
            'path' => ['type' => ['string', 'null']],
            'retryable' => ['type' => 'boolean'],
            'details' => ['type' => 'object'],
            'request_id' => ['type' => 'string'],
            'correlation_id' => ['type' => 'string'],
            'api_version' => ['type' => 'string', 'const' => $version],
        ];
        $problem = ['type' => 'object', 'required' => ['type', 'title', 'status', 'detail', 'code', 'category', 'retryable', 'details', 'request_id', 'correlation_id', 'api_version'], 'properties' => [
            'type' => ['type' => 'string', 'format' => 'uri'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'integer'],
            'detail' => ['type' => 'string'],
            'code' => $extensions['code'],
            'category' => $extensions['category'],
            'path' => $extensions['path'],
            'retryable' => $extensions['retryable'],
            'details' => $extensions['details'],
            'request_id' => $extensions['request_id'],
            'correlation_id' => $extensions['correlation_id'],
            'api_version' => $extensions['api_version'],
        ]];

        foreach ($this->operations->all($version) as $operation) {
            $parameters = [
                ['name' => 'X-Request-ID', 'in' => 'header', 'required' => false, 'schema' => [
                    'type' => 'string', 'pattern' => '^[a-zA-Z0-9_.-]{1,128}$',
                ]],
                ['name' => 'X-Correlation-ID', 'in' => 'header', 'required' => false, 'schema' => [
                    'type' => 'string', 'pattern' => '^[a-zA-Z0-9_.-]{1,128}$',
                ]],
            ];

            if ($operation->idempotency !== IdempotencyRequirement::Forbidden) {
                $parameters[] = [
                    'name' => 'Idempotency-Key', 'in' => 'header',
                    'required' => $operation->idempotency === IdempotencyRequirement::Required,
                    'description' => 'Send one opaque value of 1 to 255 bytes. Reuse it when retrying the same operation.',
                    'schema' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 255],
                ];
            }

            preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $operation->path, $matches);

            foreach ($matches[1] as $name) {
                $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
            }

            $success = ['description' => 'Success', 'content' => ['application/json' => ['schema' => [
                'type' => 'object', 'required' => ['data', 'meta'], 'properties' => [
                    'data' => $this->schemas->response($operation), 'meta' => $meta,
                ],
            ]]]];
            $responses = [$operation->successStatus => $success];
            $failure = ['description' => 'API error', 'content' => ['application/problem+json' => ['schema' => $problem]]];
            foreach ([401, 403, 404, 409, 415, 422, 429, 500, 503] as $status) {
                $responses[$status] = $failure;
            }
            $responses[429]['headers'] = ['Retry-After' => ['schema' => ['type' => 'integer', 'minimum' => 1]]];

            if ($operation->id === 'bulk-operations.cancel' || $operation->successStatus === 202) {
                $responses[202] = [...$success, 'headers' => [
                    'Location' => ['required' => true, 'schema' => ['type' => 'string', 'format' => 'uri-reference']],
                ]];
            }

            if (in_array($operation->id, ['fields.describe', 'search.describe'], true)) {
                $etag = ['schema' => ['type' => 'string']];
                $parameters[] = ['name' => 'If-None-Match', 'in' => 'header', 'schema' => ['type' => 'string']];
                $responses[200] = [...$success, 'headers' => ['ETag' => $etag]];
                $responses[304] = ['description' => 'Authorized metadata is unchanged', 'headers' => ['ETag' => $etag]];
            }

            if (in_array($operation->id, ['bulk-operations.result', 'bulk-operations.failures'], true)) {
                $responses[410] = ['description' => 'Result has expired', 'content' => $failure['content']];
            }

            $entry = [
                'operationId' => $operation->id,
                'x-packman-module' => $operation->module,
                'x-corex-ability' => $operation->ability,
                'x-corex-authentication-required' => true,
                'responses' => $responses,
            ];

            if ($operation->method === 'GET') {
                $requestSchema = JsonDocuments::map($operation->requestSchema);
                $properties = $requestSchema['properties'] ?? [];
                $required = $requestSchema['required'] ?? [];

                if (! is_array($properties) || ! is_array($required)) {
                    throw new InvalidArgumentException('Operation schema properties and required must be arrays.');
                }

                foreach ($properties as $name => $schema) {
                    $parameters[] = [
                        'name' => $name, 'in' => 'query',
                        'required' => in_array($name, $required, true),
                        'schema' => $schema,
                    ];
                }
            } else {
                $entry['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => JsonDocuments::map($operation->requestSchema)]]];
            }

            $entry['parameters'] = $parameters;
            $paths[$operation->path][strtolower($operation->method)] = $entry;
        }

        return ['openapi' => '3.1.0', 'info' => ['title' => 'Module API', 'version' => $version],
            'servers' => $serverUrl === '' ? [] : [['url' => $serverUrl]], 'paths' => (object) $paths];
    }
}
