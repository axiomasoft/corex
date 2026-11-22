<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Laravel;

use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Modules\Data\Extenders\Api;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class WebhookOperationDeclarations
{
    /** @return list<Api> */
    public static function all(): array
    {
        $endpoint = ['required', 'string', 'max:2048'];
        $keys = ['required', 'array', 'list', 'min:1', 'max:10'];
        $key = ['required', 'string', 'max:64'];
        $revision = ['required', 'integer', 'min:0'];
        $reason = ['required', 'string', 'max:191'];

        return [
            self::operation(id: 'webhook-subscriptions.list', method: 'GET', path: '/webhook-subscriptions'),
            self::operation(id: 'webhook-subscriptions.create', method: 'POST', path: '/webhook-subscriptions', rules: [
                'topic' => ['required', 'string', 'max:191'], 'topic_version' => ['required', 'integer', 'min:1'],
                'endpoint_url' => $endpoint, 'key_refs' => $keys, 'key_refs.*' => $key,
            ], status: 201),
            self::operation(id: 'webhook-subscriptions.update', method: 'PATCH', path: '/webhook-subscriptions/{id}', rules: [
                'expected_revision' => $revision, 'endpoint_url' => $endpoint, 'key_refs' => $keys, 'key_refs.*' => $key,
            ]),
            self::operation(id: 'webhook-subscriptions.disable', method: 'POST', path: '/webhook-subscriptions/{id}/disable', rules: ['expected_revision' => $revision]),
            self::operation(id: 'webhook-deliveries.list', method: 'GET', path: '/webhook-deliveries', rules: [
                'status' => ['sometimes', 'string', 'max:32'], 'subscription_id' => ['sometimes', 'string', 'max:64'],
                'cursor' => ['sometimes', 'string', 'max:4096'], 'limit' => ['sometimes', 'integer', 'between:1,100'],
            ]),
            self::operation(id: 'webhook-deliveries.range-replay', method: 'POST', path: '/webhook-deliveries/replay', rules: [
                'subscription_id' => ['required', 'string', 'max:64'], 'from' => ['required', 'date_format:Y-m-d\TH:i:sP'],
                'to' => ['required', 'date_format:Y-m-d\TH:i:sP'], 'max' => ['required', 'integer', 'between:1,100'],
                'cursor' => ['sometimes', 'string', 'max:4096'], 'audit_reason' => $reason,
            ]),
            self::operation(id: 'webhook-deliveries.show', method: 'GET', path: '/webhook-deliveries/{id}'),
            self::operation(id: 'webhook-deliveries.attempts', method: 'GET', path: '/webhook-deliveries/{id}/attempts'),
            self::operation(id: 'webhook-deliveries.replay', method: 'POST', path: '/webhook-deliveries/{id}/replay', rules: ['audit_reason' => $reason]),
        ];
    }

    /** @param array<string,list<string>> $rules */
    private static function operation(string $id, string $method, string $path, array $rules = [], int $status = 200): Api
    {
        $properties = [];
        $required = [];
        foreach ($rules as $key => $fieldRules) {
            if (str_contains($key, '.')) {
                continue;
            }
            $properties[$key] = match (true) {
                in_array('array', $fieldRules, true) => ['type' => 'array', 'items' => ['type' => 'string']],
                in_array('integer', $fieldRules, true) => ['type' => 'integer'],
                default => ['type' => 'string'],
            };

            if (in_array('required', $fieldRules, true)) {
                $required[] = $key;
            }
        }
        $ability = str_contains($path, '/replay') ? 'webhooks.replay' : 'webhooks.manage';
        $api = Api::operation(id: $id, method: $method, path: $path, handler: WebhookOperation::class, ability: $ability,
            requestSchema: ['type' => 'object', ...($properties === [] ? [] : ['properties' => $properties]), 'required' => $required, 'additionalProperties' => false],
            responseSchema: ['type' => 'object'], rules: $rules, version: 'v2', successStatus: $status)->requiresScopes($ability);

        return $method === 'GET' ? $api : $api->asCommand()->idempotency('required')->effect('tenant_transactional');
    }
}
