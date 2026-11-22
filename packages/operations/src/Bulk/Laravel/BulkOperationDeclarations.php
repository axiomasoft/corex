<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Modules\Data\Extenders\Api;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class BulkOperationDeclarations
{
    /** @return list<Api> */
    public static function all(): array
    {
        $submit = self::operation(name: 'submit', method: 'POST', path: '/bulk-operations', rules: [
            'kind' => ['required', 'string', 'in:import,export'],
            'source' => ['required', 'string', 'max:191'],
            'row_limit' => ['sometimes', 'integer', 'between:1,500'],
            'time_limit_seconds' => ['sometimes', 'integer', 'between:1,30'],
        ], status: 202)->asBulkCommand()->idempotency('required')->effect('tenant_transactional')->cost(['base' => 1]);
        $submit->bulkExecutionEnabled = true;

        return [
            $submit,
            self::operation(name: 'show', method: 'GET', path: '/bulk-operations/{id}'),
            self::operation(name: 'cancel', method: 'POST', path: '/bulk-operations/{id}/cancel')->asCommand()->idempotency('required')->effect('tenant_transactional'),
            self::operation(name: 'result', method: 'GET', path: '/bulk-operations/{id}/result'),
            self::operation(name: 'failures', method: 'GET', path: '/bulk-operations/{id}/failures', rules: [
                'after' => ['sometimes', 'string', 'max:512'],
                'limit' => ['sometimes', 'integer', 'between:1,100'],
            ]),
        ];
    }

    /** @param array<string,list<string>> $rules */
    private static function operation(string $name, string $method, string $path, array $rules = [], int $status = 200): Api
    {
        $properties = [];
        $required = [];
        foreach ($rules as $key => $fieldRules) {
            $properties[$key] = ['type' => in_array('integer', $fieldRules, true) ? 'integer' : 'string'];

            if (in_array('required', $fieldRules, true)) {
                $required[] = $key;
            }
        }
        $response = $status === 202 ? [
            'type' => 'object',
            'required' => ['operation_id', 'status_url'],
            'properties' => ['operation_id' => ['type' => 'string'], 'status_url' => ['type' => 'string']],
        ] : match ($name) {
            'show', 'cancel' => ['type' => 'object', 'required' => ['operation_id', 'status'], 'properties' => [
                'operation_id' => ['type' => 'string'],
                'status' => ['type' => 'string', 'enum' => array_column(BulkOperationStatus::cases(), 'value')],
                ...($name === 'show' ? [
                    'progress' => ['oneOf' => [['type' => 'object'], ['type' => 'array', 'maxItems' => 0]]],
                    'has_failures' => ['type' => 'boolean'],
                    'cancel_url' => ['type' => 'string', 'format' => 'uri-reference'],
                    'result_url' => ['type' => 'string', 'format' => 'uri-reference'],
                    'failures_url' => ['type' => 'string', 'format' => 'uri-reference'],
                ] : []),
            ]],
            default => ['type' => 'object'],
        };

        return Api::operation(id: 'bulk-operations.'.$name, method: $method, path: $path,
            handler: $name === 'submit' ? BulkSubmitGateway::class : BulkManagementOperation::class,
            ability: 'bulk.manage',
            requestSchema: ['type' => 'object', ...($properties === [] ? [] : ['properties' => $properties]), 'required' => $required, 'additionalProperties' => false],
            responseSchema: $response, rules: $rules, version: 'v2', successStatus: $status)->requiresScopes('bulk.manage');
    }
}
