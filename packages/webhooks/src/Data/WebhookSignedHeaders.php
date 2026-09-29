<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Data;

use InvalidArgumentException;

final readonly class WebhookSignedHeaders
{
    /** @param list<string> $signatures */
    public function __construct(
        public string $id,
        public int $timestamp,
        public array $signatures,
    ) {
        if ($id === '' || str_contains($id, '.') || preg_match('/[\x00-\x20\x7f]/', $id) === 1) {
            throw new InvalidArgumentException('Webhook delivery id is not signable.');
        }

        if ($timestamp < 0) {
            throw new InvalidArgumentException('Webhook timestamp must be a Unix timestamp.');
        }

        if (count($signatures) < 1 || count($signatures) > 2) {
            throw new InvalidArgumentException('Webhook signatures must contain one or two values.');
        }

        foreach ($signatures as $signature) {
            if (preg_match('/\Av1,[A-Za-z0-9+\/]{43}=\z/D', $signature) !== 1) {
                throw new InvalidArgumentException('Webhook signature has an invalid wire format.');
            }
        }
    }

    /** @return array{webhook-id: string, webhook-timestamp: string, webhook-signature: string} */
    public function toArray(): array
    {
        return [
            'webhook-id' => $this->id,
            'webhook-timestamp' => (string) $this->timestamp,
            'webhook-signature' => implode(' ', $this->signatures),
        ];
    }
}
