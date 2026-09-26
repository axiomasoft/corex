<?php

declare(strict_types=1);

return [
    'schema' => 'corex-public-error-contract/1',
    'contracts' => [
        ['owner' => 'corex/api', 'code' => 'webhook_replay_expired', 'category' => 'conflict', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal'],
        ['owner' => 'corex/api', 'code' => 'webhook_endpoint_changed', 'category' => 'conflict', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal'],
        ['owner' => 'corex/api', 'code' => 'webhook_subscription_cap', 'category' => 'validation', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal'],
        ['owner' => 'corex/api', 'code' => 'unknown_message_contract', 'category' => 'validation', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal'],
        ['owner' => 'corex/api', 'code' => 'not_found', 'category' => 'not_found', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal'],
        ['owner' => 'corex/api', 'code' => 'account_unavailable', 'category' => 'authorization', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal'],
        ['owner' => 'corex/api', 'code' => 'authentication_required', 'category' => 'authentication', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal+error-mapper-status-map'],
        ['owner' => 'corex/api', 'code' => 'catalogue_limit', 'category' => 'authorization', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal'],
        ['owner' => 'corex/api', 'code' => 'conflict', 'category' => 'conflict', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal+error-mapper-status-map'],
        ['owner' => 'corex/api', 'code' => 'context_required', 'category' => 'authorization', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal'],
        ['owner' => 'corex/api', 'code' => 'forbidden', 'category' => 'authorization', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal+error-mapper-status-map'],
        ['owner' => 'corex/api', 'code' => 'internal_error', 'category' => 'internal', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal+error-mapper-status-map'],
        ['owner' => 'corex/api', 'code' => 'module_unavailable', 'category' => 'not_found', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal'],
        ['owner' => 'corex/api', 'code' => 'operation_not_found', 'category' => 'not_found', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal'],
        ['owner' => 'corex/api', 'code' => 'rate_limited', 'category' => 'rate_limit', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'error-mapper-status-map'],
        ['owner' => 'corex/api', 'code' => 'request_rejected', 'category' => 'validation', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'error-mapper-status-map'],
        ['owner' => 'corex/api', 'code' => 'resource_not_found', 'category' => 'not_found', 'retryable' => false, 'path' => null, 'detail_keys' => [], 'declaration' => 'literal+error-mapper-status-map'],
        ['owner' => 'corex/api', 'code' => 'validation_failed', 'category' => 'validation', 'retryable' => false, 'path' => null, 'detail_keys' => ['*'], 'declaration' => 'literal+error-mapper-status-map'],
        ['owner' => 'corex/attributes', 'code' => 'attribute_schema_conflict', 'category' => 'conflict', 'retryable' => false, 'path' => null, 'detail_keys' => ['reason'], 'declaration' => 'literal'],
        ['owner' => 'corex/attributes', 'code' => 'attribute_schema_conflict', 'category' => 'conflict', 'retryable' => true, 'path' => null, 'detail_keys' => ['reason'], 'declaration' => 'literal'],
        ['owner' => 'corex/attributes', 'code' => 'attribute_schema_retry_exhausted', 'category' => 'conflict', 'retryable' => false, 'path' => null, 'detail_keys' => ['reason'], 'declaration' => 'literal'],
        ['owner' => 'corex/attributes', 'code' => 'attributes_access_denied', 'category' => 'authorization', 'retryable' => false, 'path' => null, 'detail_keys' => ['reason'], 'declaration' => 'literal'],
        ['owner' => 'corex/attributes', 'code' => 'attributes_engine_unavailable', 'category' => 'unavailable', 'retryable' => false, 'path' => null, 'detail_keys' => ['reason'], 'declaration' => 'literal'],
    ],
    'dynamic_adapters' => [
        ['owner' => 'corex/api', 'source' => 'packages/api/src/Internal/Webhook/WebhookTopicCatalogue.php', 'adapter' => 'declared-webhook-topic-admission', 'sha256' => '82cb20cc47e939f4ab417a23c52e74b9b9eda2c7129381c8775c3f219b5ca2c2'],
        ['owner' => 'corex/api', 'source' => 'packages/api/src/ApiError.php', 'adapter' => 'api-error-instance-descriptor', 'sha256' => 'e9d15c3aea97ace422ba103dfb368c2686122b6cf5f53f0f5d39b6d2ad55b0c6'],
        ['owner' => 'corex/api', 'source' => 'packages/api/src/Internal/ErrorMapper.php', 'adapter' => 'descriptor-and-framework-status-map', 'sha256' => 'c544268bcca1db076f283e0a18a701ff1294d737eeca684277d78868957c893a'],
    ],
];
