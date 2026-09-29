<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Fixtures;

use CoreX\Webhooks\Contracts\WebhookHttpTransport;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookHttpResult;

/**
 * In-process receiver. It verifies synthetic HMAC signatures and deduplicates
 * on delivery id. It never opens a socket.
 */
final class LocalWebhookReceiver implements WebhookHttpTransport
{
    public int $effects = 0;

    /** @var array<string, true> */
    private array $seen = [];

    /** @param list<string> $secrets Standard Webhooks whsec_ secrets. */
    public function __construct(
        private readonly array $secrets,
        private readonly int $now,
        private readonly int $windowSeconds = 300,
    ) {}

    public function send(ValidatedWebhookRequest $request): WebhookHttpResult
    {
        $id = (string) ($request->signatureHeaders['webhook-id'] ?? '');
        $timestamp = (string) ($request->signatureHeaders['webhook-timestamp'] ?? '');
        $presented = (string) ($request->signatureHeaders['webhook-signature'] ?? '');

        if (! $this->authentic($id, $timestamp, $request->body, $presented)) {
            return new WebhookHttpResult(401, null, 'signature_invalid', 0);
        }

        if (isset($this->seen[$id])) {
            return new WebhookHttpResult(204, null, null, 0);
        }

        $this->seen[$id] = true;
        $this->effects++;

        return new WebhookHttpResult(204, null, null, 1);
    }

    private function authentic(string $id, string $timestamp, string $body, string $presented): bool
    {
        if ($id === '' || preg_match('/\A\d+\z/D', $timestamp) !== 1) {
            return false;
        }

        if (abs($this->now - (int) $timestamp) > $this->windowSeconds) {
            return false;
        }

        $content = $id.'.'.$timestamp.'.'.$body;

        foreach ($this->secrets as $secret) {
            $key = $this->decode($secret);

            if ($key === null) {
                continue;
            }

            $expected = 'v1,'.base64_encode(hash_hmac('sha256', $content, $key, true));

            foreach (explode(' ', $presented) as $candidate) {
                if (hash_equals($expected, $candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function decode(string $secret): ?string
    {
        if (! str_starts_with($secret, 'whsec_')) {
            return null;
        }

        $bytes = base64_decode(substr($secret, strlen('whsec_')), true);

        return is_string($bytes) && strlen($bytes) === 32 ? $bytes : null;
    }
}
