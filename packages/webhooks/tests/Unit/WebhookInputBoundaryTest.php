<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Unit;

use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Webhooks\Data\ValidatedWebhookRequest;
use CoreX\Webhooks\Data\WebhookDestination;
use CoreX\Webhooks\Data\WebhookHttpResult;
use CoreX\Webhooks\Data\WebhookSignedHeaders;
use CoreX\Webhooks\Data\WebhookTopic;
use CoreX\Webhooks\Enums\WebhookFailureReason;
use CoreX\Webhooks\Internal\WebhookUrlPolicy;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookInputBoundaryTest extends TestCase
{
    public function test_canonical_destination_and_payload_limits_keep_the_exact_wire_values(): void
    {
        $destination = WebhookDestination::fromUrl('HTTPS://Hooks.Example.COM:443');
        self::assertSame(['https', 'hooks.example.com', 443, '/'], [$destination->scheme, $destination->host, $destination->port, $destination->path]);
        $headers = ['webhook-id' => 'delivery-1', 'webhook-timestamp' => '0', 'webhook-signature' => 'v1,'.str_repeat('A', 43).'='];
        foreach ([1, 10] as $timeout) {
            $body = str_repeat('x', 20480);
            $request = new ValidatedWebhookRequest('hooks.example.com', 'https://hooks.example.com/path', '93.184.216.34', $body, $headers, $timeout);
            self::assertSame($body, $request->body);
            self::assertSame($headers, $request->signatureHeaders);
            self::assertSame($timeout, $request->timeoutSeconds);
            self::assertSame('93.184.216.34', $request->address);
        }
        self::assertSame(10, (new ValidatedWebhookRequest('hooks.example.com', 'https://hooks.example.com/', '2606:2800:220:1:248:1893:25c8:1946', '', $headers))->timeoutSeconds);
    }

    #[DataProvider('invalidRequests')]
    public function test_request_rejects_each_independent_admission_failure(array $overrides, string $message): void
    {
        $args = ['canonicalHost' => 'hooks.example.com', 'url' => 'https://hooks.example.com/', 'address' => '93.184.216.34', 'body' => '{}', 'signatureHeaders' => ['webhook-id' => 'delivery', 'webhook-timestamp' => '0', 'webhook-signature' => 'signature'], 'timeoutSeconds' => 10];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        new ValidatedWebhookRequest(...array_replace($args, $overrides));
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidRequests(): iterable
    {
        foreach (['http://hooks.example.com/', 'https://other.example.com/', 'https://user@hooks.example.com/', 'https://user:password@hooks.example.com/', 'https://hooks.example.com/?query', 'https://hooks.example.com/#fragment', 'https://hooks.example.com:444/'] as $url) {
            yield $url => [['url' => $url], 'Webhook request URL is not a canonical HTTPS endpoint.'];
        }
        yield 'invalid IP' => [['address' => 'not-an-address'], 'Webhook request address is invalid.'];
        yield 'body 20481' => [['body' => str_repeat('x', 20481)], 'Webhook request body exceeds 20 KiB.'];
        foreach ([0, 11] as $timeout) {
            yield 'timeout '.$timeout => [['timeoutSeconds' => $timeout], 'Webhook request timeout must be between 1 and 10 seconds.'];
        }
        $headers = ['webhook-id' => 'delivery', 'webhook-timestamp' => '0', 'webhook-signature' => 'signature'];
        foreach (array_keys($headers) as $key) {
            $missing = $headers;
            unset($missing[$key]);
            yield 'missing '.$key => [['signatureHeaders' => $missing], 'Webhook request must contain exactly three signature headers.'];
            foreach (['', "bad\0", "bad\n", "bad\x7f", 7] as $index => $value) {
                yield 'invalid '.$key.' '.$index => [['signatureHeaders' => array_replace($headers, [$key => $value])], 'Webhook signature header is invalid.'];
            }
        }
        yield 'four headers' => [['signatureHeaders' => $headers + ['authorization' => 'secret']], 'Webhook request must contain exactly three signature headers.'];
        yield 'oversized headers' => [['signatureHeaders' => array_replace($headers, ['webhook-signature' => str_repeat('x', 16384)])], 'Webhook request headers exceed 16 KiB.'];
    }

    #[DataProvider('httpResults')]
    public function test_http_result_boundaries_are_admitted_without_altering_the_observed_values(?int $status, ?string $retry, ?WebhookFailureReason $error, int $duration): void
    {
        $result = new WebhookHttpResult($status, $retry, $error, $duration);
        self::assertSame([$status, $retry, $error, $duration], [$result->status, $result->retryAfter, $result->errorCode, $result->durationMs]);
    }

    /** @return iterable<array{?int, ?string, ?WebhookFailureReason, int}> */
    public static function httpResults(): iterable
    {
        yield [100, null, null, 0];
        yield [599, str_repeat('9', 255), null, 1];
        yield [null, null, WebhookFailureReason::DnsUnavailable, 0];
    }

    #[DataProvider('invalidHttpResults')]
    public function test_http_result_invalid_wire_values_are_rejected(?int $status, ?string $retry, ?WebhookFailureReason $error, int $duration, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        new WebhookHttpResult($status, $retry, $error, $duration);
    }

    /** @return iterable<array{?int, ?string, ?WebhookFailureReason, int, string}> */
    public static function invalidHttpResults(): iterable
    {
        yield [99, null, null, 0, 'Webhook HTTP status is invalid.'];
        yield [600, null, null, 0, 'Webhook HTTP status is invalid.'];
        yield [null, null, null, 0, 'Webhook result must contain a status or a safe error code.'];
        yield [200, null, null, -1, 'Webhook duration must be non-negative.'];
        foreach (['', str_repeat('9', 256), "1\0", "1\n", "1\x7f"] as $value) {
            yield [429, $value, null, 0, 'Webhook Retry-After value is invalid.'];
        }
    }

    public function test_signatures_keep_one_or_two_ordered_values_and_a_zero_timestamp(): void
    {
        $first = 'v1,'.str_repeat('A', 43).'=';
        $second = 'v1,'.str_repeat('B', 43).'=';
        self::assertSame(['webhook-id' => 'delivery-1', 'webhook-timestamp' => '0', 'webhook-signature' => $first.' '.$second], (new WebhookSignedHeaders('delivery-1', 0, [$first, $second]))->toArray());
        self::assertSame($first, (new WebhookSignedHeaders('delivery-1', 1, [$first]))->toArray()['webhook-signature']);
    }

    #[DataProvider('invalidSignatures')]
    public function test_unsigned_or_ambiguous_signature_frames_are_rejected(string $id, int $timestamp, array $signatures, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        new WebhookSignedHeaders($id, $timestamp, $signatures);
    }

    /** @return iterable<array{string, int, list<string>, string}> */
    public static function invalidSignatures(): iterable
    {
        $signature = 'v1,'.str_repeat('A', 43).'=';
        foreach (['', 'delivery.1', 'delivery 1', "delivery\0", "delivery\x7f"] as $id) {
            yield [$id, 0, [$signature], 'Webhook delivery id is not signable.'];
        }
        yield ['delivery', -1, [$signature], 'Webhook timestamp must be a Unix timestamp.'];
        yield ['delivery', 0, [], 'Webhook signatures must contain one or two values.'];
        yield ['delivery', 0, [$signature, $signature, $signature], 'Webhook signatures must contain one or two values.'];
        foreach (['v2,'.str_repeat('A', 43).'=', 'v1,'.str_repeat('A', 42).'=', 'v1,'.str_repeat('A', 44).'=', 'v1,'.str_repeat('A', 43), $signature."\n"] as $value) {
            yield ['delivery', 0, [$value], 'Webhook signature has an invalid wire format.'];
        }
    }

    public function test_topic_pii_rank_and_subscription_flags_keep_the_closed_vocabulary(): void
    {
        foreach (['none' => [0, false, false], 'pseudonymous' => [1, false, false], 'personal' => [2, true, false], 'restricted' => [3, true, false], 'secret' => [4, false, true]] as $class => [$rank, $sensitive, $secret]) {
            $schema = ['value' => 'string'];
            $topic = new WebhookTopic('contact.created', 1, 'v1', $schema, hash('sha256', JsonCanonical::encode($schema)), $class, 'contact.projector');
            self::assertSame('contact.created.v1', $topic->id());
            self::assertSame($rank, $topic->rank());
            self::assertSame($sensitive, $topic->sensitive());
            self::assertSame($secret, $topic->secret());
        }
    }

    #[DataProvider('addressBoundaries')]
    public function test_public_address_policy_checks_range_endpoints_and_adjacent_addresses(string $address, bool $allowed): void
    {
        self::assertSame($allowed, WebhookUrlPolicy::allowsPublicAddress($address));
    }

    /** @return iterable<array{string, bool}> */
    public static function addressBoundaries(): iterable
    {
        foreach (['0.255.255.255', '10.255.255.255', '100.64.0.0', '100.127.255.255', '127.255.255.255', '169.254.255.255', '172.16.0.0', '172.31.255.255', '192.0.0.255', '192.0.2.255', '192.168.255.255', '198.18.0.0', '198.19.255.255', '198.51.100.255', '203.0.113.255', '239.255.255.255', '255.255.255.255', '::', '::1', '::ffff:93.184.216.34', '2001:1ff:ffff:ffff:ffff:ffff:ffff:ffff', '2001:db8:ffff:ffff:ffff:ffff:ffff:ffff', '2002:ffff:ffff:ffff:ffff:ffff:ffff:ffff', '3fff:fff:ffff:ffff:ffff:ffff:ffff:ffff', '4000::', 'not-an-address'] as $address) {
            yield [$address, false];
        }
        foreach (['1.0.0.0', '9.255.255.255', '11.0.0.0', '100.63.255.255', '100.128.0.0', '126.255.255.255', '128.0.0.0', '169.253.255.255', '169.255.0.0', '172.15.255.255', '172.32.0.0', '192.0.1.0', '192.0.3.0', '192.167.255.255', '192.169.0.0', '198.17.255.255', '198.20.0.0', '198.51.99.255', '198.51.101.0', '203.0.112.255', '203.0.114.0', '223.255.255.255', '2001:200::', '2001:db7:ffff:ffff:ffff:ffff:ffff:ffff', '2001:db9::', '2003::', '3ffe:ffff:ffff:ffff:ffff:ffff:ffff:ffff'] as $address) {
            yield [$address, true];
        }
    }
}
