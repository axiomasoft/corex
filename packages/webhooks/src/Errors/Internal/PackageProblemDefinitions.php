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
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookEgressDenied, type: 'urn:corex:problem:webhooks.webhook_egress_denied', defaultStatus: 422, title: 'Webhook destination is denied by tenant egress policy.', retryable: false, category: ErrorCategory::Validation);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookPayloadTooLarge, type: 'urn:corex:problem:webhooks.webhook_payload_too_large', defaultStatus: 422, title: 'Webhook payload is too large.', retryable: false, category: ErrorCategory::Validation);
        yield new ProblemDefinition(code: WebhooksErrorCode::UnknownMessageContract, type: 'urn:corex:problem:webhooks.unknown_message_contract', defaultStatus: 422, title: 'Unknown message contract.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::UnknownMessageSchema, type: 'urn:corex:problem:webhooks.unknown_message_schema', defaultStatus: 422, title: 'Unknown message schema.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookEndpointChanged, type: 'urn:corex:problem:webhooks.webhook_endpoint_changed', defaultStatus: 409, title: 'Webhook endpoint changed.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookReplayExpired, type: 'urn:corex:problem:webhooks.webhook_replay_expired', defaultStatus: 409, title: 'Webhook replay expired.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookSecretDenied, type: 'urn:corex:problem:webhooks.webhook_secret_denied', defaultStatus: 403, title: 'Webhook secret denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookSensitiveDenied, type: 'urn:corex:problem:webhooks.webhook_sensitive_denied', defaultStatus: 403, title: 'Webhook sensitive denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookSubscriptionCap, type: 'urn:corex:problem:webhooks.webhook_subscription_cap', defaultStatus: 422, title: 'Webhook subscription cap.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
    }
}
