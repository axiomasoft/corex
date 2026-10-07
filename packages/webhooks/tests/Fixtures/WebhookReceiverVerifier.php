<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Fixtures;

use Psr\Clock\ClockInterface;

final readonly class WebhookReceiverVerifier
{
    private const int TOLERANCE_SECONDS = 300;

    /** @param list<string> $secrets */
    public function __construct(
        private ClockInterface $clock,
        private array $secrets,
    ) {}

    /** @param array<string, mixed> $headers */
    public function verify(array $headers, string $rawBody): bool
    {
        if (count($headers) !== 3
            || ! isset($headers['webhook-id'], $headers['webhook-timestamp'], $headers['webhook-signature'])
            || ! is_string($headers['webhook-id'])
            || ! is_string($headers['webhook-timestamp'])
            || ! is_string($headers['webhook-signature'])) {
            return false;
        }

        $id = $headers['webhook-id'];
        $timestampWire = $headers['webhook-timestamp'];

        if ($id === ''
            || str_contains($id, '.')
            || preg_match('/[\x00-\x20\x7f]/', $id) === 1
            || preg_match('/\A(?:0|[1-9][0-9]*)\z/D', $timestampWire) !== 1) {
            return false;
        }

        $timestamp = filter_var($timestampWire, FILTER_VALIDATE_INT);

        if (! is_int($timestamp)) {
            return false;
        }

        $now = $this->clock->now()->getTimestamp();

        if ($timestamp < $now - self::TOLERANCE_SECONDS || $timestamp > $now + self::TOLERANCE_SECONDS) {
            return false;
        }

        $signatures = explode(' ', $headers['webhook-signature']);

        if (count($signatures) < 1 || count($signatures) > 2) {
            return false;
        }

        $content = "{$id}.{$timestampWire}.{$rawBody}";
        $decodedSignatures = [];

        foreach ($signatures as $signature) {
            $actual = $this->decodeSignature($signature);

            if ($actual === null) {
                return false;
            }

            $decodedSignatures[] = $actual;
        }

        foreach ($decodedSignatures as $actual) {
            foreach ($this->secrets as $secret) {
                $key = $this->decodeSecret($secret);

                if ($key === null) {
                    continue;
                }

                $expected = hash_hmac(
                    algo: 'sha256',
                    data: $content,
                    key: $key,
                    binary: true,
                );

                if (hash_equals($expected, $actual)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function decodeSignature(string $signature): ?string
    {
        if (! str_starts_with($signature, 'v1,')) {
            return null;
        }

        return $this->decodeBase64(substr($signature, 3));
    }

    private function decodeSecret(string $secret): ?string
    {
        if (! str_starts_with($secret, 'whsec_')) {
            return null;
        }

        return $this->decodeBase64(substr($secret, 6));
    }

    private function decodeBase64(string $encoded): ?string
    {
        $bytes = base64_decode(string: $encoded, strict: true);

        if ($bytes === false || strlen($bytes) !== 32 || ! hash_equals(base64_encode($bytes), $encoded)) {
            return null;
        }

        return $bytes;
    }
}
