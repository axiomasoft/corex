<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Operations\Errors\ApiError;
use CoreX\Webhooks\Contracts\WebhookPayloadProjector;
use CoreX\Webhooks\Data\WebhookTopic;
use InvalidArgumentException;

/** Immutable projection of explicit topic mappings onto the declared message registry. */
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

            $contract = $this->declared($topic->name, $topic->version);
            $this->assertMatchesContract($topic, $contract);
            $indexed[$topic->id()] = $topic;
        }

        ksort($indexed);
        $this->topics = $indexed;
    }

    public function get(string $name, int $version): WebhookTopic
    {
        $this->declared($name, $version);
        $topic = $this->topics["{$name}.v{$version}"] ?? null;

        if (! $topic instanceof WebhookTopic) {
            throw self::rejected('unknown_message_contract', 'The message contract is not a declared webhook topic.');
        }

        return $topic;
    }

    /**
     * @param  array<string, string>  $schema
     */
    public function assertSchema(string $name, int $version, array $schema): WebhookTopic
    {
        $topic = $this->get($name, $version);

        if ($schema !== $topic->schema) {
            throw self::rejected('unknown_message_schema', 'The message schema is not the declared webhook topic schema.');
        }

        return $topic;
    }

    public function admit(WebhookTopic $topic, bool $sensitiveAbility, bool $policyAllows): void
    {
        $this->get($topic->name, $topic->version);

        if ($topic->secret()) {
            throw self::rejected('webhook_secret_denied', 'Secret webhook topics cannot be subscribed.', 403, ErrorCategory::Authorization);
        }

        if ($topic->sensitive() && (! $sensitiveAbility || ! $policyAllows)) {
            throw self::rejected('webhook_sensitive_denied', 'Sensitive webhook topics require an explicit ability.', 403, ErrorCategory::Authorization);
        }
    }

    private function declared(string $name, int $version): MessageContract
    {
        if ($version < 1 || ! $this->contracts->has($name, $version)) {
            throw self::rejected('unknown_message_contract', 'The message contract is not declared.');
        }

        return $this->contracts->get($name, $version);
    }

    private function assertMatchesContract(WebhookTopic $topic, MessageContract $contract): void
    {
        if ($topic->secret()) {
            throw self::rejected('webhook_secret_denied', 'Secret webhook topics cannot be declared.', 403, ErrorCategory::Authorization);
        }

        if ($topic->rank() > $contract->maxPiiClass->rank()) {
            throw self::rejected('unknown_message_contract', 'The webhook topic exceeds the declared message PII class.');
        }
    }

    private static function rejected(
        string $code,
        string $message,
        int $status = 422,
        ErrorCategory $category = ErrorCategory::Validation,
    ): ApiError {
        return new ApiError($code, $status, $message, category: $category);
    }
}
