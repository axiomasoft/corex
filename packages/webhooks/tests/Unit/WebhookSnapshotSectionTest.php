<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Unit;

use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Internal\WebhookSnapshotSection;
use PHPUnit\Framework\TestCase;

final class WebhookSnapshotSectionTest extends TestCase
{
    public function test_contract_filters_major_and_sorts_topics_versions_and_required_fields(): void
    {
        $topic = static fn (string $name, int $version, string $major): WebhookTopic => new WebhookTopic(
            $name, $version, $major, ['z' => 'string', 'a' => 'integer'], hash('sha256', JsonCanonical::encode(['z' => 'string', 'a' => 'integer'])), 'none', 'export',
        );
        $section = new WebhookSnapshotSection([
            $topic('z.created', 1, 'v1'), $topic('a.created', 2, 'v1'),
            $topic('a.created', 1, 'v1'), $topic('other.created', 1, 'v2'),
        ]);
        $document = $section->document('v1');

        self::assertSame('webhooks.json', $section->file());
        self::assertSame('corex-webhook-contract/1', $document['schema']);
        self::assertSame(['a.created', 'a.created', 'z.created'], array_column($document['topics'], 'name'));
        self::assertSame([1, 2, 1], array_column($document['topics'], 'version'));
        self::assertSame(['a', 'z'], $document['topics'][0]['payload']['required']);
        self::assertSame(['type' => 'integer'], $document['topics'][0]['payload']['properties']['a']);
        self::assertSame(['guarantee' => 'at-least-once', 'ordering' => 'none'], $document['delivery']);
        self::assertFalse($document['pii']['secret_subscribable']);
        self::assertSame(20 * 1024, $document['payload_limit_bytes']);
        self::assertSame([], $section->document('v3')['topics']);
        self::assertSame([
            'bulk' => [
                'failure_page_max' => 100,
                'result_ttl_seconds' => 86400,
                'states' => ['cancelled', 'cancelling', 'completed', 'expired', 'failed', 'pending', 'running'],
                'tombstone_ttl_seconds' => 2592000,
            ],
            'delivery' => ['guarantee' => 'at-least-once', 'ordering' => 'none'],
            'payload_limit_bytes' => 20480,
            'pii' => ['classes' => ['none', 'pseudonymous', 'personal', 'restricted', 'secret'], 'secret_subscribable' => false],
            'replay' => ['body_retention_seconds' => 604800, 'horizon_seconds' => 604800, 'max_count' => 3, 'range_max' => 100, 'range_window_seconds' => 86400],
            'retry' => ['attempts' => 12, 'horizon_seconds' => 259200, 'lease_seconds' => 30, 'maximum_delay_seconds' => 3600, 'minimum_delay_seconds' => 1],
            'rotation' => [
                'algorithm' => 'hmac-sha256', 'content' => 'delivery-id.timestamp.raw-body',
                'headers' => ['webhook-id', 'webhook-timestamp', 'webhook-signature'],
                'id_semantics' => 'delivery-id', 'max_active_keys' => 2, 'prefix' => 'whsec_',
                'secret_bytes' => 32, 'signature' => 'v1', 'timestamp_window_seconds' => 300,
            ],
            'schema' => 'corex-webhook-contract/1',
            'topics' => [],
        ], (new WebhookSnapshotSection)->document('v1'));
        self::assertSame([
            'major' => 'v1', 'name' => 'a.created',
            'payload' => ['properties' => ['z' => ['type' => 'string'], 'a' => ['type' => 'integer']], 'required' => ['a', 'z'], 'type' => 'object'],
            'pii_class' => 'none', 'version' => 1,
        ], $document['topics'][0]);
    }
}
