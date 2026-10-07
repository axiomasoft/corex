<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class ValidatedWebhookRequest
{
    private const MAX_BODY_BYTES = 20 * 1024;

    private const MAX_HEADER_BYTES = 16 * 1024;

    /** @var array{webhook-id: string, webhook-timestamp: string, webhook-signature: string} */
    public array $signatureHeaders;

    /**
     * @param  array{webhook-id: string, webhook-timestamp: string, webhook-signature: string}  $signatureHeaders
     */
    public function __construct(
        public string $canonicalHost,
        public string $url,
        public string $address,
        public string $body,
        array $signatureHeaders,
        public int $timeoutSeconds = 10,
    ) {
        self::validateInput(canonicalHost: $canonicalHost, url: $url, address: $address, body: $body, signatureHeaders: $signatureHeaders, timeoutSeconds: $timeoutSeconds);
        $this->signatureHeaders = [
            'webhook-id' => $signatureHeaders['webhook-id'],
            'webhook-timestamp' => $signatureHeaders['webhook-timestamp'],
            'webhook-signature' => $signatureHeaders['webhook-signature'],
        ];
    }

    /** @param array<array-key, mixed> $signatureHeaders */
    private static function validateInput(
        string $canonicalHost,
        string $url,
        string $address,
        string $body,
        array $signatureHeaders,
        int $timeoutSeconds = 10,
    ): void {
        $parts = parse_url($url);

        if ($parts === false
            || ($parts['scheme'] ?? null) !== 'https'
            || ($parts['host'] ?? null) !== $canonicalHost
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (($parts['port'] ?? 443) !== 443)) {
            throw new InvalidArgumentException('Webhook request URL is not a canonical HTTPS endpoint.');
        }

        if (filter_var($address, FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Webhook request address is invalid.');
        }

        if (strlen($body) > self::MAX_BODY_BYTES) {
            throw new InvalidArgumentException('Webhook request body exceeds 20 KiB.');
        }

        if (count($signatureHeaders) !== 3
            || ! isset(
                $signatureHeaders['webhook-id'],
                $signatureHeaders['webhook-timestamp'],
                $signatureHeaders['webhook-signature'],
            )) {
            throw new InvalidArgumentException('Webhook request must contain exactly three signature headers.');
        }

        $headerBytes = 0;

        foreach ($signatureHeaders as $name => $value) {
            if (! is_string($value)
                || $value === ''
                || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
                throw new InvalidArgumentException('Webhook signature header is invalid.');
            }

            $headerBytes += strlen($name) + 2 + strlen($value) + 2;
        }

        if ($headerBytes > self::MAX_HEADER_BYTES) {
            throw new InvalidArgumentException('Webhook request headers exceed 16 KiB.');
        }

        if ($timeoutSeconds < 1 || $timeoutSeconds > 10) {
            throw new InvalidArgumentException('Webhook request timeout must be between 1 and 10 seconds.');
        }
    }
}
