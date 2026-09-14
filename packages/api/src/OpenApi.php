<?php

declare(strict_types=1);

namespace CoreX\Api;

use CoreX\Api\Contracts\SchemaContributor;

/** Build-time installed-code projection; not a public endpoint or a grant of access. */
final class OpenApi
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
        $meta = ['type' => 'object', 'required' => ['request_id', 'api_version'], 'properties' => [
            'request_id' => ['type' => 'string'], 'api_version' => ['type' => 'string', 'const' => $version],
        ]];
        $error = ['type' => 'object', 'required' => ['data', 'error', 'meta'], 'properties' => [
            'data' => ['type' => 'null'],
            'error' => ['type' => 'object', 'required' => ['code', 'message', 'details'], 'properties' => [
                'code' => ['type' => 'string'], 'message' => ['type' => 'string'], 'details' => ['type' => 'object'],
            ]],
            'meta' => $meta,
        ]];

        foreach ($this->operations->all($version) as $operation) {
            $parameters = [['name' => 'X-Request-ID', 'in' => 'header', 'required' => false, 'schema' => [
                'type' => 'string', 'pattern' => '^[a-zA-Z0-9_.-]{1,128}$',
            ]]];
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

            foreach ([401, 403, 404, 409, 422, 500] as $status) {
                $entry['responses'][$status] = ['description' => 'API error', 'content' => ['application/json' => ['schema' => $error]]];
            }

            if ($operation->method === 'GET') {
                foreach (($operation->requestSchema['properties'] ?? []) as $name => $schema) {
                    $entry['parameters'][] = [
                        'name' => $name, 'in' => 'query',
                        'required' => in_array($name, $operation->requestSchema['required'] ?? [], true),
                        'schema' => $schema,
                    ];
                }
            } else {
                $entry['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $operation->requestSchema]]];
            }

            $paths[$operation->path][strtolower($operation->method)] = $entry;
        }

        return ['openapi' => '3.1.0', 'info' => ['title' => 'Module API', 'version' => $version],
            'servers' => $serverUrl === '' ? [] : [['url' => $serverUrl]], 'paths' => (object) $paths];
    }
}
