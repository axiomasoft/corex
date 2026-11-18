<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Operations\OpenApi\Contracts\ContractSnapshotSection;
use CoreX\Webhooks\Data\WebhookTopic;
use Packman\Attributes\Internal;

/** @internal Installed-code webhook document, independent of tenant activation. */
#[Internal]
final readonly class WebhookSnapshotSection implements ContractSnapshotSection
{
    /** @var list<string> */
    private const array BULK_STATES = [
        'cancelled', 'cancelling', 'completed', 'expired', 'failed', 'pending', 'running',
    ];

    /** @param list<WebhookTopic> $topics */
    public function __construct(private array $topics = []) {}

    public function file(): string
    {
        return 'webhooks.json';
    }

    /** @return array<string, mixed> */
    public function document(string $major): array
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
            $properties[$field] = ['type' => $type];
            $required[] = $field;
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
}
