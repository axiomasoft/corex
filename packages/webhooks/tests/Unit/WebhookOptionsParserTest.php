<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Unit;

use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Laravel\WebhookOptionsParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

final class WebhookOptionsParserTest extends TestCase
{
    public function test_parses_topics_and_canonical_projector_mapping(): void
    {
        $schema = ['id' => 'string'];
        $row = ['name' => 'orders.created', 'version' => 1, 'major' => 'v1', 'schema' => $schema,
            'pii_class' => 'personal', 'projector_service' => 'export'];
        $topic = new WebhookTopic('billing.created', 1, 'v1', $schema, hash('sha256', JsonCanonical::encode($schema)), 'none', 'export');
        $options = WebhookOptionsParser::fromArray([
            'delivery_enabled' => true, 'worker_ready' => true,
            'organization_denied_cidrs' => ['203.0.113.0/24'],
            'projectors' => ['z.export' => 'ZExport', 'export' => 'Export'], 'topics' => [$row, $topic],
        ]);
        self::assertTrue($options->deliveryEnabled);
        self::assertSame(['export' => 'Export', 'z.export' => 'ZExport'], $options->projectors);
        self::assertSame(['orders.created.v1', 'billing.created.v1'], array_map(static fn (WebhookTopic $topic): string => $topic->id(), $options->topics));
        self::assertSame(['203.0.113.0/24'], $options->organizationDeniedCidrs);
        self::assertSame($topic, $options->topics[1]);
    }

    #[DataProvider('invalidConfigurations')]
    public function test_rejects_invalid_configuration_with_field_diagnostics(array $config, string $field): void
    {
        try {
            WebhookOptionsParser::fromArray($config);
            self::fail('Invalid configuration was accepted.');
        } catch (Throwable $exception) {
            self::assertStringContainsString($field, $exception->getMessage());
        }
    }

    public static function invalidConfigurations(): array
    {
        return [
            [['delivery_enabled' => 'yes'], 'delivery_enabled'],
            [['delivery_enabled' => true], 'worker_ready'],
            [['lease_seconds' => 10], 'lease_seconds'],
            [['lease_seconds' => 0], 'lease_seconds'],
            [['history_retention_seconds' => 1], 'body_retention_seconds'],
            [['replay_horizon_seconds' => 1], 'replay_horizon_seconds'],
            [['organization_denied_cidrs' => 'bad'], 'organization_denied_cidrs'],
            [['organization_denied_cidrs' => ['']], 'organization_denied_cidrs.0'],
            [['projectors' => ['service']], 'projectors'],
            [['projectors' => ['Invalid' => '']], 'projectors'],
            [['topics' => 'bad'], 'topics'],
            [['topics' => ['bad']], 'topics.0'],
            [['topics' => [[]]], 'topics.0'],
            [['topics' => [['schema' => [3 => false]]]], 'topics.0'],
        ];
    }
}
