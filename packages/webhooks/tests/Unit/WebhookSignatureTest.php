<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Unit;

use CoreX\Time\Testing\FrozenClock;
use CoreX\Webhooks\Internal\HmacWebhookSigner;
use CoreX\Webhooks\Internal\WebhookKeyRing;
use CoreX\Webhooks\Tests\Fixtures\WebhookReceiverVerifier;
use CoreX\Webhooks\Tests\TestCase;
use DateTimeImmutable;
use InvalidArgumentException;
use Packman\Config\Data\SecretRef;
use PHPUnit\Framework\Attributes\DataProvider;

final class WebhookSignatureTest extends TestCase
{
    private const string SECRET = 'whsec_AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';

    private const int TIMESTAMP = 1_700_000_000;

    public function test_independent_known_hmac_bytes_and_exact_headers(): void
    {
        $signed = $this->signer(self::SECRET)->sign(
            deliveryId: 'delivery_123',
            timestamp: self::TIMESTAMP,
            rawBody: '{"event":"created"}',
            secretRefs: ['primary'],
        );

        $this->assertSame([
            'webhook-id' => 'delivery_123',
            'webhook-timestamp' => '1700000000',
            'webhook-signature' => 'v1,N0I40J9c82MglWTa7Uo3WQeDMrzK3HtIGJS9MLkS1jI=',
        ], $signed->toArray());
    }

    #[DataProvider('invalidSecrets')]
    public function test_secret_format_base64_and_length_are_strict(string $secret): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Webhook secret has an invalid format.');

        $this->signer($secret)->sign(
            deliveryId: 'delivery_123',
            timestamp: self::TIMESTAMP,
            rawBody: '{}',
            secretRefs: ['primary'],
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSecrets(): iterable
    {
        yield 'missing prefix' => [substr(self::SECRET, 6)];
        yield 'invalid base64' => ['whsec_!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!'];
        yield '31 bytes' => ['whsec_'.base64_encode(str_repeat('a', 31))];
        yield '33 bytes' => ['whsec_'.base64_encode(str_repeat('a', 33))];
        yield 'non-canonical unpadded base64' => [rtrim(self::SECRET, '=')];
    }

    public function test_receiver_denies_raw_tamper_stale_and_future_timestamps(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('@'.self::TIMESTAMP));
        $receiver = new WebhookReceiverVerifier(
            clock: $clock,
            secrets: [self::SECRET],
        );
        $body = '{"event":"created"}';
        $headers = $this->signer(self::SECRET)->sign(
            deliveryId: 'delivery_123',
            timestamp: self::TIMESTAMP,
            rawBody: $body,
            secretRefs: ['primary'],
        )->toArray();

        $this->assertTrue($receiver->verify(headers: $headers, rawBody: $body));
        $this->assertFalse($receiver->verify(headers: $headers, rawBody: '{"event":"changed"}'));

        $malformedTrailingSignature = $headers;
        $malformedTrailingSignature['webhook-signature'] .= ' malformed';
        $this->assertFalse($receiver->verify(headers: $malformedTrailingSignature, rawBody: $body));

        $stale = $this->signer(self::SECRET)->sign(
            deliveryId: 'delivery_123',
            timestamp: self::TIMESTAMP - 301,
            rawBody: $body,
            secretRefs: ['primary'],
        )->toArray();
        $future = $this->signer(self::SECRET)->sign(
            deliveryId: 'delivery_123',
            timestamp: self::TIMESTAMP + 301,
            rawBody: $body,
            secretRefs: ['primary'],
        )->toArray();

        $this->assertFalse($receiver->verify(headers: $stale, rawBody: $body));
        $this->assertFalse($receiver->verify(headers: $future, rawBody: $body));
    }

    private function signer(string $secret): HmacWebhookSigner
    {
        $keys = new WebhookKeyRing;
        $keys->provision(
            secretRef: 'primary',
            secret: new SecretRef($secret),
            notBefore: 0,
        );

        return new HmacWebhookSigner(keyRing: $keys);
    }
}
