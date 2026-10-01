<?php

declare(strict_types=1);

namespace CoreX\Operations\OpenApi\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Contracts\SchemaContributor;
use CoreX\Operations\OpenApi\Contracts\OpenApiDocuments;

/** Build-time installed-code projection; not a public endpoint or a grant of access. */
final class OpenApiDocumentGenerator implements OpenApiDocuments
{
    public function __construct(
        private readonly OperationRegistry $operations,
        private readonly SchemaContributor $schemas,
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
        $error = ['type' => 'object', 'required' => ['data', 'error', 'meta'], 'properties' => [
            'data' => ['type' => 'null'],
            'error' => ['type' => 'object', 'required' => ['code', 'message', 'category', 'path', 'retryable', 'details'], 'properties' => [
                'code' => $extensions['code'],
                'message' => ['type' => 'string'],
                'category' => $extensions['category'],
                'path' => $extensions['path'],
                'retryable' => $extensions['retryable'],
                'details' => $extensions['details'],
            ]],
            'meta' => $meta,
        ]];
        $problem = ['type' => 'object', 'required' => ['type', 'title', 'status', 'detail', 'instance', 'code', 'category', 'path', 'retryable', 'details', 'request_id', 'correlation_id', 'api_version'], 'properties' => [
            'type' => ['type' => 'string', 'format' => 'uri'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'integer'],
            'detail' => ['type' => 'string'],
            'instance' => ['type' => 'string', 'format' => 'uri'],
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
            preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $operation->path, $matches);

            foreach ($matches[1] as $name) {
                $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']];
            }

            $entry = [
                'operationId' => $operation->id,
                'x-corex-module' => $operation->module,
                'x-corex-ability' => $operation->ability,
                'x-corex-authentication-required' => true,
                'parameters' => $parameters,
                'responses' => [
                    $operation->successStatus => ['description' => 'Success', 'content' => ['application/json' => ['schema' => [
                        'type' => 'object', 'required' => ['data', 'error', 'meta'], 'properties' => [
                            'data' => $this->schemas->response($operation), 'error' => ['type' => 'null'], 'meta' => $meta,
                        ],
                    ]]]],
                ],
            ];

            foreach ([401, 403, 404, 406, 409, 422, 500] as $status) {
                $entry['responses'][$status] = ['description' => 'API error', 'content' => [
                    'application/json' => ['schema' => $error],
                    'application/problem+json' => ['schema' => $problem],
                ]];
            }

            if ($operation->method === 'GET') {
                foreach ((JsonDocuments::map($operation->requestSchema)['properties'] ?? []) as $name => $schema) {
                    $entry['parameters'][] = [
                        'name' => $name, 'in' => 'query',
                        'required' => in_array($name, JsonDocuments::map($operation->requestSchema)['required'] ?? [], true),
                        'schema' => $schema,
                    ];
                }
            } else {
                $entry['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => JsonDocuments::map($operation->requestSchema)]]];
            }

            $paths[$operation->path][strtolower($operation->method)] = $entry;
        }

        return ['openapi' => '3.1.0', 'info' => ['title' => 'Module API', 'version' => $version],
            'servers' => $serverUrl === '' ? [] : [['url' => $serverUrl]], 'paths' => (object) $paths];
    }
}
