<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Foundation\Laravel\ReferenceWrites;
use CoreX\Messaging\Contracts\CloudEventCodec;
use CoreX\Messaging\Contracts\Inbox;
use CoreX\Messaging\Data\ConsumerId;
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
    public const string Consumer = 'api.webhooks';

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
        private DataScopeCodec $scopes,
        private CurrentWorkScope $current,
        private StoredWorkIssuer $issuer,
    ) {}

    public function accept(MessageEnvelope $message): void
    {
        $scope = $message->scope;
        $this->connection->transaction(function () use ($message, $scope): void {
            $this->current->assert($scope, $this->connection);
            ReferenceWrites::enlist($this->connection, $scope, 'axiomasoft/corex-webhooks', 'webhook-fanout', ['contract' => $message->name, 'source' => $message->source]);

            if (! $this->inbox->claim($this->connection, new ConsumerId(self::Consumer, 1), $message)) {
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
                $subscription = DatabaseValue::text(DatabaseValue::field($row, 'id'));
                $revision = DatabaseValue::integer(DatabaseValue::field($row, 'revision'));
                $endpoint = DatabaseValue::text(DatabaseValue::field($row, 'endpoint_url'));
                $key = WebhookWork::key($scope, $subscription, $message->source, $message->messageId, 'original');
                $id = substr($key, 0, 32);
                $bodyHash = hash('sha256', $body);
                $digest = WebhookWork::digest($endpoint, $bodyHash, $topic->name, $topic->version, $revision, $topic->major);
                $proof = $this->issuer->issue($scope, WebhookWork::Owner, WebhookWork::reference($id, $key, $digest));
                $this->connection->table('sys_api_webhook_deliveries')->insert([
                    ...WebhookDeliveryScope::logical($scope),
                    ...WebhookDeliveryScope::fence($scope),
                    'id' => $id,
                    'subscription_id' => $subscription,
                    'subscription_revision' => $revision,
                    'event_source' => $message->source,
                    'event_id' => $message->messageId,
                    'replay_id' => 'original',
                    'effect_key' => $key,
                    'scope_seal' => $proof->seal,
                    'topic' => $topic->name,
                    'topic_version' => $topic->version,
                    'major' => $topic->major,
                    'endpoint_snapshot' => $endpoint,
                    'scope' => JsonCanonical::encode($this->scopes->encode($scope)),
                    'body' => $body,
                    'body_hash' => $bodyHash,
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
