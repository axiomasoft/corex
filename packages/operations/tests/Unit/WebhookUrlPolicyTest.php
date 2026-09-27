<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Unit;

use CoreX\Api\Data\WebhookSignedHeaders;
use CoreX\Api\Internal\Webhook\WebhookUrlPolicy;
use CoreX\Api\Tests\TestCase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

final class WebhookUrlPolicyTest extends TestCase
{
    public function test_public_dns_set_is_canonicalized_and_pinned_deterministically(): void
    {
        $resolvedHost = null;
        $policy = new WebhookUrlPolicy(
            resolver: function (string $host) use (&$resolvedHost): array {
                $resolvedHost = $host;

                return ['2606:2800:220:1:248:1893:25c8:1946', '93.184.216.34'];
            },
        );

        $request = $policy->validate(
            url: 'HTTPS://Hooks.Example.COM/deliver',
            body: '{"event":"created"}',
            signedHeaders: $this->signedHeaders(),
        );

        $this->assertSame('hooks.example.com', $resolvedHost);
        $this->assertSame('hooks.example.com', $request->canonicalHost);
        $this->assertSame('https://hooks.example.com/deliver', $request->url);
        $this->assertSame('2606:2800:220:1:248:1893:25c8:1946', $request->address);
        $this->assertSame('{"event":"created"}', $request->body);
    }

    #[DataProvider('forbiddenAddressSets')]
    public function test_private_reserved_metadata_mapped_and_mixed_dns_sets_are_denied(array $addresses): void
    {
        $policy = new WebhookUrlPolicy(resolver: static fn (): array => $addresses);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('webhook_url_forbidden');

        $policy->validate(
            url: 'https://hooks.example.com/deliver',
            body: '{}',
            signedHeaders: $this->signedHeaders(),
        );
    }

    /** @return iterable<string, array{list<string>}> */
    public static function forbiddenAddressSets(): iterable
    {
        yield 'unspecified' => [['0.0.0.0']];
        yield 'private' => [['10.0.0.1']];
        yield 'shared address space' => [['100.64.0.1']];
        yield 'loopback' => [['127.0.0.1']];
        yield 'link local' => [['169.254.1.1']];
        yield 'metadata' => [['169.254.169.254']];
        yield 'benchmark' => [['198.18.0.1']];
        yield 'documentation' => [['203.0.113.10']];
        yield 'multicast' => [['224.0.0.1']];
        yield 'ipv6 loopback' => [['::1']];
        yield 'ipv4 mapped ipv6' => [['::ffff:127.0.0.1']];
        yield 'teredo' => [['2001::1']];
        yield 'six to four' => [['2002:c000:0204::1']];
        yield 'ipv6 documentation' => [['2001:db8::1']];
        yield 'mixed public and private' => [['93.184.216.34', '10.0.0.1']];
    }

    #[DataProvider('forbiddenUrls')]
    public function test_ambiguous_or_credential_bearing_urls_are_denied(string $url): void
    {
        $policy = new WebhookUrlPolicy(resolver: static fn (): array => ['93.184.216.34']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('webhook_url_forbidden');

        $policy->validate(
            url: $url,
            body: '{}',
            signedHeaders: $this->signedHeaders(),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function forbiddenUrls(): iterable
    {
        yield 'http' => ['http://hooks.example.com/deliver'];
        yield 'non standard port' => ['https://hooks.example.com:8443/deliver'];
        yield 'userinfo' => ['https://user:password@hooks.example.com/deliver'];
        yield 'query credentials' => ['https://hooks.example.com/deliver?token=secret'];
        yield 'fragment' => ['https://hooks.example.com/deliver#secret'];
        yield 'ip literal' => ['https://127.0.0.1/deliver'];
        yield 'ipv6 literal' => ['https://[2606:2800:220:1:248:1893:25c8:1946]/deliver'];
        yield 'ambiguous integer host' => ['https://2130706433/deliver'];
        yield 'ambiguous dotted octal' => ['https://0177.0.0.1/deliver'];
        yield 'unicode idn' => ['https://tést.example/deliver'];
        yield 'trailing dot' => ['https://hooks.example.com./deliver'];
        yield 'encoded newline' => ['https://hooks.example.com/deliver%0d%0aInjected'];
        yield 'backslash authority ambiguity' => ['https://hooks.example.com\\@127.0.0.1/deliver'];
    }

    public function test_empty_dns_is_retryable_unavailable(): void
    {
        $policy = new WebhookUrlPolicy(resolver: static fn (): array => []);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('webhook_dns_unavailable');

        $policy->validate(
            url: 'https://hooks.example.com/deliver',
            body: '{}',
            signedHeaders: $this->signedHeaders(),
        );
    }

    public function test_every_attempt_resolves_again_but_an_existing_request_keeps_its_pin(): void
    {
        $answers = [
            ['93.184.216.34'],
            ['127.0.0.1'],
        ];
        $policy = new WebhookUrlPolicy(resolver: static function () use (&$answers): array {
            return array_shift($answers) ?? [];
        });

        $firstAttempt = $policy->validate(
            url: 'https://hooks.example.com/deliver',
            body: '{}',
            signedHeaders: $this->signedHeaders(),
        );

        $this->assertSame('93.184.216.34', $firstAttempt->address);

        try {
            $policy->validate(
                url: 'https://hooks.example.com/deliver',
                body: '{}',
                signedHeaders: $this->signedHeaders(),
            );
            $this->fail('A DNS rebinding answer must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('webhook_url_forbidden', $exception->getMessage());
        }

        $this->assertSame('93.184.216.34', $firstAttempt->address);
    }

    public function test_organization_owned_ranges_are_denied_after_public_validation(): void
    {
        $policy = new WebhookUrlPolicy(
            resolver: static fn (): array => ['93.184.216.34'],
            organizationDeniedCidrs: ['93.184.216.0/24'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('webhook_url_forbidden');

        $policy->validate(
            url: 'https://hooks.example.com/deliver',
            body: '{}',
            signedHeaders: $this->signedHeaders(),
        );
    }

    public function test_request_body_and_timeout_caps_are_enforced(): void
    {
        $policy = new WebhookUrlPolicy(resolver: static fn (): array => ['93.184.216.34']);

        try {
            $policy->validate(
                url: 'https://hooks.example.com/deliver',
                body: str_repeat('x', 20 * 1024 + 1),
                signedHeaders: $this->signedHeaders(),
            );
            $this->fail('An oversized request body must be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Webhook request body exceeds 20 KiB.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook request timeout must be between 1 and 10 seconds.');

        $policy->validate(
            url: 'https://hooks.example.com/deliver',
            body: '{}',
            signedHeaders: $this->signedHeaders(),
            timeoutSeconds: 11,
        );
    }

    private function signedHeaders(): WebhookSignedHeaders
    {
        return new WebhookSignedHeaders(
            id: 'delivery_123',
            timestamp: 1_700_000_000,
            signatures: ['v1,'.base64_encode(str_repeat('a', 32))],
        );
    }
}
