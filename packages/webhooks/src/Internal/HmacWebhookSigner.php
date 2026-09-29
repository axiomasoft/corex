<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Config\SecretRef;
use CoreX\Webhooks\Contracts\WebhookSigner;
use CoreX\Webhooks\Data\WebhookSignedHeaders;
use InvalidArgumentException;

final readonly class HmacWebhookSigner implements WebhookSigner
{
    private const SECRET_PREFIX = 'whsec_';

    private const SECRET_BYTES = 32;

    public function __construct(
        private WebhookKeyRing $keyRing,
    ) {}

    public function sign(
        string $deliveryId,
        int $timestamp,
        string $rawBody,
        array $secretRefs,
    ): WebhookSignedHeaders {
        $content = "{$deliveryId}.{$timestamp}.{$rawBody}";
        $keys = $this->keyRing->resolveForSigning(
            secretRefs: $secretRefs,
            timestamp: $timestamp,
        );
        $signatures = [];

        foreach ($keys as $secret) {
            $bytes = $this->decodeSecret($secret);
            $digest = hash_hmac(
                algo: 'sha256',
                data: $content,
                key: $bytes,
                binary: true,
            );
            $signatures[] = 'v1,'.base64_encode($digest);
        }

        return new WebhookSignedHeaders(
            id: $deliveryId,
            timestamp: $timestamp,
            signatures: $signatures,
        );
    }

    private function decodeSecret(SecretRef $secret): string
    {
        $wire = $secret->reveal();

        if (! str_starts_with($wire, self::SECRET_PREFIX)) {
            throw new InvalidArgumentException('Webhook secret has an invalid format.');
        }

        $encoded = substr($wire, strlen(self::SECRET_PREFIX));
        $bytes = base64_decode(string: $encoded, strict: true);

        if ($bytes === false
            || strlen($bytes) !== self::SECRET_BYTES
            || ! hash_equals(base64_encode($bytes), $encoded)) {
            throw new InvalidArgumentException('Webhook secret has an invalid format.');
        }

        return $bytes;
    }
}
