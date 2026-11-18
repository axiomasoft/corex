<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Messaging\Contracts\CloudEventCodec;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Data\MessageEnvelope;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use DateInterval;
use Illuminate\Database\Connection;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;

/** Materializes one delivery per active subscription inside the inbox transaction. */
#[Internal]
final readonly class WebhookFanout
{
    public const string Consumer = 'api.webhooks.v1';

    public const int BodyLimit = 20 * 1024;

    /**
     * @param  array<string, WebhookPayloadProjector>  $projectors
     */
    public function __construct(
        private Connection $connection,
        private Inbox $inbox,
        private WebhookTopicCatalogue $catalogue,
        private array $projectors,
        private ClockInterface $clock,
        private CloudEventCodec $codec,
    ) {}

    public function accept(MessageEnvelope $message): void
    {
        $this->connection->transaction(function () use ($message): void {
            if (! $this->inbox->claim(consumer: self::Consumer, messageId: $message->messageId, name: $message->name)) {
                return;
            }

            $topic = $this->catalogue->get(name: $message->name, version: $message->contractVersion);
            $projector = $this->projectors[$topic->projectorService] ?? null;

            if (! $projector instanceof WebhookPayloadProjector) {
                throw new RuntimeException('Webhook projector is not installed.');
            }

            $rows = $this->connection->table('sys_api_webhook_subscriptions')
                ->where('status', 'active')
                ->where('topic', $message->name)
                ->where('topic_version', $message->contractVersion)
                ->orderBy('id')
                ->get();

            if ($rows->count() > 100) {
                throw new RuntimeException('Webhook fan-out exceeds 100 matching subscriptions.');
            }

            $projected = $projector->project($message, $topic);
            $encoded = $this->codec->encodeExternal(message: $message, data: $projected);
            $oversize = strlen($encoded) > self::BodyLimit;
            $body = $oversize ? $this->codec->encodeExternal(message: $message, data: ['error' => WebhookFailureReason::PayloadTooLarge->value]) : $encoded;
            $now = $this->clock->now();

            foreach ($rows as $row) {
                $this->connection->table('sys_api_webhook_deliveries')->insertOrIgnore([
                    'id' => substr(hash('sha256', DatabaseValue::text(DatabaseValue::field(row: $row, name: 'id')).'|'.$message->messageId), 0, 32),
                    'subscription_id' => DatabaseValue::field(row: $row, name: 'id'),
                    'subscription_revision' => DatabaseValue::field(row: $row, name: 'revision'),
                    'event_id' => $message->messageId,
                    'topic' => $topic->name,
                    'topic_version' => $topic->version,
                    'major' => $topic->major,
                    'endpoint_snapshot' => DatabaseValue::field(row: $row, name: 'endpoint_url'),
                    'scope' => JsonCanonical::encode($message->scope),
                    'body' => $body,
                    'body_hash' => hash('sha256', $body),
                    'status' => $oversize ? 'dead' : 'pending',
                    'attempt' => 0,
                    'replay_count' => 0,
                    'lease_owner' => null,
                    'lease_until' => null,
                    'next_attempt_at' => $now,
                    'created_at' => $now,
                    'finished_at' => $oversize ? $now : null,
                    'expires_at' => $now->add(new DateInterval('P7D')),
                    'last_error' => $oversize ? WebhookFailureReason::PayloadTooLarge->value : null,
                ]);
            }
        });
    }
}
