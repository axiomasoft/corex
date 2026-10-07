<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use Closure;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookDestination;
use CoreX\Webhooks\Data\WebhookSignedHeaders;
use InvalidArgumentException;
use RuntimeException;

final readonly class WebhookUrlPolicy
{
    private const MAX_DNS_RECORDS = 64;

    private const MAX_CNAME_DEPTH = 8;

    /** @var list<string> */
    private const DENIED_IPV4_CIDRS = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
    ];

    /** @var list<string> */
    private const DENIED_IPV6_CIDRS = [
        '2001::/23',
        '2001:db8::/32',
        '2002::/16',
        '3fff::/20',
    ];

    /** @var Closure(string): list<string> */
    private Closure $resolver;

    /**
     * @param  null|callable(string): list<string>  $resolver
     * @param  list<string>  $organizationDeniedCidrs
     */
    public function __construct(
        ?callable $resolver = null,
        private array $organizationDeniedCidrs = [],
    ) {
        $this->resolver = $resolver === null
            ? $this->resolveDns(...)
            : Closure::fromCallable($resolver);

        foreach ($organizationDeniedCidrs as $cidr) {
            self::parseCidr($cidr);
        }
    }

    public function validate(
        string $url,
        string $body,
        WebhookSignedHeaders $signedHeaders,
        int $timeoutSeconds = 10,
    ): ValidatedWebhookRequest {
        $destination = WebhookDestination::fromUrl($url);
        $canonicalHost = $destination->host;
        $canonicalUrl = $destination->scheme.'://'.$canonicalHost.$destination->path;
        $addresses = ($this->resolver)($canonicalHost);

        if ($addresses === []) {
            throw new RuntimeException('webhook_dns_unavailable');
        }

        if (count($addresses) > self::MAX_DNS_RECORDS) {
            throw new InvalidArgumentException('webhook_url_forbidden');
        }

        $vetted = [];

        foreach ($addresses as $address) {
            $packed = @inet_pton($address);

            if ($packed === false
                || ! self::allowsPublicAddress($address)
                || $this->matchesOrganizationRange($address)) {
                throw new InvalidArgumentException('webhook_url_forbidden');
            }

            $vetted[bin2hex($packed)] = strtolower((string) inet_ntop($packed));
        }

        ksort($vetted, SORT_STRING);

        return new ValidatedWebhookRequest(
            canonicalHost: $canonicalHost,
            url: $canonicalUrl,
            address: array_values($vetted)[0],
            body: $body,
            signatureHeaders: $signedHeaders->toArray(),
            timeoutSeconds: $timeoutSeconds,
        );
    }

    public static function allowsPublicAddress(string $address): bool
    {
        $packed = @inet_pton($address);

        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            foreach (self::DENIED_IPV4_CIDRS as $cidr) {
                if (self::matchesCidr($packed, $cidr)) {
                    return false;
                }
            }

            return true;
        }

        if (substr($packed, 0, 10) === str_repeat("\0", 10)
            && substr($packed, 10, 2) === "\xff\xff") {
            return false;
        }

        if (! self::matchesCidr($packed, '2000::/3')) {
            return false;
        }

        foreach (self::DENIED_IPV6_CIDRS as $cidr) {
            if (self::matchesCidr($packed, $cidr)) {
                return false;
            }
        }

        return true;
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

        foreach ($labels as $label) {
            if (preg_match('/\A[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\z/D', $label) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private function resolveDns(string $host): array
    {
        /** @var list<array{string, int}> $pending */
        $pending = [[$host, 0]];
        $visited = [];
        $addresses = [];

        while ($pending !== []) {
            [$current, $depth] = array_shift($pending);

            if (isset($visited[$current])) {
                throw new RuntimeException('webhook_dns_unavailable');
            }

            $visited[$current] = true;
            $records = dns_get_record($current, DNS_A | DNS_AAAA | DNS_CNAME);

            if ($records === false) {
                throw new RuntimeException('webhook_dns_unavailable');
            }

            foreach ($records as $record) {
                if (isset($record['ip']) && is_string($record['ip'])) {
                    $addresses[] = $record['ip'];
                }

                if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $addresses[] = $record['ipv6'];
                }

                if (! isset($record['target']) || ! is_string($record['target'])) {
                    continue;
                }

                if ($depth >= self::MAX_CNAME_DEPTH) {
                    throw new RuntimeException('webhook_dns_unavailable');
                }

                $target = strtolower(rtrim($record['target'], '.'));

                if (! self::isCanonicalDnsName($target)) {
                    throw new InvalidArgumentException('webhook_url_forbidden');
                }

                $pending[] = [$target, $depth + 1];
            }

            if (count($addresses) > self::MAX_DNS_RECORDS) {
                throw new InvalidArgumentException('webhook_url_forbidden');
            }
        }

        return array_values(array_unique($addresses));
    }

    private function matchesOrganizationRange(string $address): bool
    {
        $packed = @inet_pton($address);

        if ($packed === false) {
            return true;
        }

        foreach ($this->organizationDeniedCidrs as $cidr) {
            if (self::matchesCidr($packed, $cidr)) {
                return true;
            }
        }

        return false;
    }

    private static function matchesCidr(string $packedAddress, string $cidr): bool
    {
        [$packedNetwork, $prefix] = self::parseCidr($cidr);

        if (strlen($packedAddress) !== strlen($packedNetwork)) {
            return false;
        }

        $fullBytes = intdiv($prefix, 8);
        $remainingBits = $prefix % 8;

        if ($fullBytes > 0
            && substr($packedAddress, 0, $fullBytes) !== substr($packedNetwork, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($packedAddress[$fullBytes]) & $mask) === (ord($packedNetwork[$fullBytes]) & $mask);
    }

    /** @return array{string, int} */
    private static function parseCidr(string $cidr): array
    {
        $parts = explode('/', $cidr, 2);
        $packed = @inet_pton($parts[0]);
        $prefix = isset($parts[1]) && preg_match('/\A\d+\z/D', $parts[1]) === 1
            ? (int) $parts[1]
            : -1;

        if ($packed === false || $prefix < 0 || $prefix > strlen($packed) * 8) {
            throw new InvalidArgumentException('Webhook denied range is invalid.');
        }

        return [$packed, $prefix];
    }
}
