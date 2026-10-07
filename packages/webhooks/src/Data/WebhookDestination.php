<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

/** Canonical HTTPS destination shared by URL admission and host egress policies. */
#[Api(since: '1.0')]
final readonly class WebhookDestination
{
    private const int MAX_URL_BYTES = 2048;

    public function __construct(
        public string $scheme,
        public string $host,
        public int $port,
        public string $path,
    ) {}

    public static function fromUrl(string $url): self
    {
        if ($url === ''
            || strlen($url) > self::MAX_URL_BYTES
            || preg_match('/[^\x21-\x7e]/', $url) === 1
            || str_contains($url, '\\')
            || preg_match('/%(?:00|0a|0d)/i', $url) === 1) {
            throw new InvalidArgumentException('webhook_url_forbidden');
        }

        $parts = parse_url($url);

        if ($parts === false
            || strtolower($parts['scheme'] ?? '') !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (($parts['port'] ?? 443) !== 443)) {
            throw new InvalidArgumentException('webhook_url_forbidden');
        }

        $host = strtolower($parts['host']);

        if (! self::isCanonicalDnsName($host) || @inet_pton($host) !== false) {
            throw new InvalidArgumentException('webhook_url_forbidden');
        }

        $path = $parts['path'] ?? '/';

        if ($path === '') {
            $path = '/';
        }

        if (! str_starts_with($path, '/')) {
            throw new InvalidArgumentException('webhook_url_forbidden');
        }

        return new self(scheme: 'https', host: $host, port: 443, path: $path);
    }

    private static function isCanonicalDnsName(string $host): bool
    {
        if ($host === ''
            || strlen($host) > 253
            || $host !== strtolower($host)
            || str_ends_with($host, '.')
            || preg_match('/\A[0-9.]+\z/D', $host) === 1) {
            return false;
        }

        $labels = explode('.', $host);

        if (count($labels) < 2) {
            return false;
        }

        return array_all($labels, fn ($label) => ! (preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D', $label) !== 1));
    }
}
