<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Errors\Internal;

use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Webhooks\Errors\Enums\WebhooksErrorCode;

/** @internal The owning package contributes its safe error catalogue at boot. */
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(WebhooksErrorCode::WebhookEgressDenied, 'urn:corex:problem:webhooks.webhook_egress_denied', 422, 'Webhook destination is denied by tenant egress policy.', false, ErrorCategory::Validation);
        yield new ProblemDefinition(WebhooksErrorCode::WebhookPayloadTooLarge, 'urn:corex:problem:webhooks.webhook_payload_too_large', 422, 'Webhook payload is too large.', false, ErrorCategory::Validation);
        yield new ProblemDefinition(WebhooksErrorCode::UnknownMessageContract, 'urn:corex:problem:webhooks.unknown_message_contract', 422, 'Unknown message contract.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(WebhooksErrorCode::UnknownMessageSchema, 'urn:corex:problem:webhooks.unknown_message_schema', 422, 'Unknown message schema.', false, ErrorCategory::Validation, []);
        yield new ProblemDefinition(WebhooksErrorCode::WebhookEndpointChanged, 'urn:corex:problem:webhooks.webhook_endpoint_changed', 409, 'Webhook endpoint changed.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(WebhooksErrorCode::WebhookReplayExpired, 'urn:corex:problem:webhooks.webhook_replay_expired', 409, 'Webhook replay expired.', false, ErrorCategory::Conflict, []);
        yield new ProblemDefinition(WebhooksErrorCode::WebhookSecretDenied, 'urn:corex:problem:webhooks.webhook_secret_denied', 403, 'Webhook secret denied.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(WebhooksErrorCode::WebhookSensitiveDenied, 'urn:corex:problem:webhooks.webhook_sensitive_denied', 403, 'Webhook sensitive denied.', false, ErrorCategory::Authorization, []);
        yield new ProblemDefinition(WebhooksErrorCode::WebhookSubscriptionCap, 'urn:corex:problem:webhooks.webhook_subscription_cap', 422, 'Webhook subscription cap.', false, ErrorCategory::Validation, []);
    }
}
