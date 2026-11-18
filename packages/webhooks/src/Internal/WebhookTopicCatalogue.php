<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Errors\Enums\WebhooksErrorCode;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Packman\Errors\Enums\ErrorCategory;

/** Immutable projection of explicit topic mappings onto the declared message registry. */
#[Internal]
final readonly class WebhookTopicCatalogue
{
    /** @var array<string, WebhookTopic> */
    private array $topics;

    /**
     * @param  list<WebhookTopic>  $topics
     * @param  array<mixed>  $projectors
     */
    public function __construct(
        private MessageContractRegistry $contracts,
        array $topics,
        array $projectors,
    ) {
        $indexed = [];

        foreach ($projectors as $service => $projector) {
            if (! is_string($service) || ! $projector instanceof WebhookPayloadProjector || str_contains($service, '\\')) {
                throw new InvalidArgumentException('Webhook projectors must be an installed service map.');
            }
        }

        foreach ($topics as $topic) {
            if (isset($indexed[$topic->id()])) {
                throw new InvalidArgumentException("Webhook topic [{$topic->id()}] is declared twice.");
            }

            if (! array_key_exists($topic->projectorService, $projectors)) {
                throw new InvalidArgumentException("Webhook projector [{$topic->projectorService}] is not installed.");
            }

            $contract = $this->declared(name: $topic->name, version: $topic->version);
            $this->assertMatchesContract(topic: $topic, contract: $contract);
            $indexed[$topic->id()] = $topic;
        }

        ksort($indexed);
        $this->topics = $indexed;
    }

    public function get(string $name, int $version): WebhookTopic
    {
        $this->declared(name: $name, version: $version);
        $topic = $this->topics["{$name}.v{$version}"] ?? null;

        if (! $topic instanceof WebhookTopic) {
            throw self::rejected(code: WebhooksErrorCode::UnknownMessageContract, message: 'The message contract is not a declared webhook topic.');
        }

        return $topic;
    }

    /**
     * @param  array<string, string>  $schema
     */
    public function assertSchema(string $name, int $version, array $schema): WebhookTopic
    {
        $topic = $this->get(name: $name, version: $version);

        if ($schema !== $topic->schema) {
            throw self::rejected(code: WebhooksErrorCode::UnknownMessageSchema, message: 'The message schema is not the declared webhook topic schema.');
        }

        return $topic;
    }

    public function admit(WebhookTopic $topic, bool $sensitiveAbility, bool $policyAllows): void
    {
        $this->get(name: $topic->name, version: $topic->version);

        if ($topic->secret()) {
            throw self::rejected(code: WebhooksErrorCode::WebhookSecretDenied, message: 'Secret webhook topics cannot be subscribed.', status: 403, category: ErrorCategory::Authorization);
        }

        if ($topic->sensitive() && (! $sensitiveAbility || ! $policyAllows)) {
            throw self::rejected(code: WebhooksErrorCode::WebhookSensitiveDenied, message: 'Sensitive webhook topics require an explicit ability.', status: 403, category: ErrorCategory::Authorization);
        }
    }

    private function declared(string $name, int $version): MessageContract
    {
        if ($version < 1 || ! $this->contracts->has(name: $name, version: $version)) {
            throw self::rejected(code: WebhooksErrorCode::UnknownMessageContract, message: 'The message contract is not declared.');
        }

        return $this->contracts->get(name: $name, version: $version);
    }

    private function assertMatchesContract(WebhookTopic $topic, MessageContract $contract): void
    {
        if ($topic->secret()) {
            throw self::rejected(code: WebhooksErrorCode::WebhookSecretDenied, message: 'Secret webhook topics cannot be declared.', status: 403, category: ErrorCategory::Authorization);
        }

        if ($topic->rank() > $contract->maxPiiClass->rank()) {
            throw self::rejected(code: WebhooksErrorCode::UnknownMessageContract, message: 'The webhook topic exceeds the declared message PII class.');
        }
    }

    private static function rejected(
        WebhooksErrorCode $code,
        string $message,
        int $status = 422,
        ErrorCategory $category = ErrorCategory::Validation,
    ): ApiError {
        return new ApiError(errorCode: $code, status: $status, message: $message, category: $category);
    }
}
