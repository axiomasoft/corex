<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Errors\Internal;

use CoreX\Webhooks\Errors\Enums\WebhooksErrorCode;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemDefinitionProvider;
use Packman\Errors\Data\ProblemDefinition;
use Packman\Errors\Enums\ErrorCategory;

/** @internal The owning package contributes its safe error catalogue at boot. */
#[Internal]
final class PackageProblemDefinitions implements ProblemDefinitionProvider
{
    public function definitions(): iterable
    {
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookEgressDenied, type: 'urn:packman:problem:webhooks.webhook_egress_denied', defaultStatus: 422, title: 'Webhook destination is denied by tenant egress policy.', retryable: false, category: ErrorCategory::Validation);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookPayloadTooLarge, type: 'urn:packman:problem:webhooks.webhook_payload_too_large', defaultStatus: 422, title: 'Webhook payload is too large.', retryable: false, category: ErrorCategory::Validation);
        yield new ProblemDefinition(code: WebhooksErrorCode::UnknownMessageContract, type: 'urn:packman:problem:webhooks.unknown_message_contract', defaultStatus: 422, title: 'Unknown message contract.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::UnknownMessageSchema, type: 'urn:packman:problem:webhooks.unknown_message_schema', defaultStatus: 422, title: 'Unknown message schema.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookEndpointChanged, type: 'urn:packman:problem:webhooks.webhook_endpoint_changed', defaultStatus: 409, title: 'Webhook endpoint changed.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookReplayExpired, type: 'urn:packman:problem:webhooks.webhook_replay_expired', defaultStatus: 409, title: 'Webhook replay expired.', retryable: false, category: ErrorCategory::Conflict, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookSecretDenied, type: 'urn:packman:problem:webhooks.webhook_secret_denied', defaultStatus: 403, title: 'Webhook secret denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookSensitiveDenied, type: 'urn:packman:problem:webhooks.webhook_sensitive_denied', defaultStatus: 403, title: 'Webhook sensitive denied.', retryable: false, category: ErrorCategory::Authorization, detailKeys: []);
        yield new ProblemDefinition(code: WebhooksErrorCode::WebhookSubscriptionCap, type: 'urn:packman:problem:webhooks.webhook_subscription_cap', defaultStatus: 422, title: 'Webhook subscription cap.', retryable: false, category: ErrorCategory::Validation, detailKeys: []);
    }
}
