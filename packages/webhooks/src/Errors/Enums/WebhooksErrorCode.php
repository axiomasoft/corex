<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Errors\Enums;

use Packman\Attributes\Api;
use Packman\Errors\Contracts\ErrorCode;

#[Api(since: '1.0')]
enum WebhooksErrorCode: string implements ErrorCode
{
    case UnknownMessageContract = 'webhooks.unknown_message_contract';
    case UnknownMessageSchema = 'webhooks.unknown_message_schema';
    case WebhookEgressDenied = 'webhooks.webhook_egress_denied';
    case WebhookEndpointChanged = 'webhooks.webhook_endpoint_changed';
    case WebhookPayloadTooLarge = 'webhooks.webhook_payload_too_large';
    case WebhookReplayExpired = 'webhooks.webhook_replay_expired';
    case WebhookSecretDenied = 'webhooks.webhook_secret_denied';
    case WebhookSensitiveDenied = 'webhooks.webhook_sensitive_denied';
    case WebhookSubscriptionCap = 'webhooks.webhook_subscription_cap';

    public function value(): string
    {
        return $this->value;
    }
}
