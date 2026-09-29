<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class WebhookSubscription
{
    /** @param  array<mixed>  $keyRefs */
    public function __construct(
        public string $id,
        public int $revision,
        public string $major,
        public string $topic,
        public int $topicVersion,
        public string $endpointUrl,
        public string $status,
        public string $maxPiiClass,
        public array $keyRefs,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]{0,63}\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Webhook subscription id must be an opaque identifier.');
        }

        if ($revision < 0 || $topicVersion < 1) {
            throw new InvalidArgumentException('Webhook subscription revision and topic version are invalid.');
        }

        if (! in_array($status, ['active', 'disabled'], true)) {
            throw new InvalidArgumentException('Webhook subscription status must be active or disabled.');
        }

        if (! in_array($maxPiiClass, WebhookTopic::CLASSES, true) || $maxPiiClass === 'secret') {
            throw new InvalidArgumentException('Webhook subscription PII class is not subscribable.');
        }

        $parts = parse_url($endpointUrl);

        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || ! isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Webhook endpoint must be an https URL without credentials.');
        }

        foreach ($keyRefs as $ref) {
            if (! is_string($ref) || preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $ref) !== 1) {
                throw new InvalidArgumentException('Webhook key refs must be SecretRef identifiers.');
            }
        }
    }
}
