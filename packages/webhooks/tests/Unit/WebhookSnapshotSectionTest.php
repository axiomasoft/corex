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
    }
}
