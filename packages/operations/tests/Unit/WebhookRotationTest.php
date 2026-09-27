<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Unit;

use CoreX\Api\Internal\Webhook\HmacWebhookSigner;
use CoreX\Api\Internal\Webhook\WebhookKeyRing;
use CoreX\Api\Tests\Fixtures\WebhookReceiverVerifier;
use CoreX\Api\Tests\TestCase;
use CoreX\Support\SecretRef;
use CoreX\Support\Time\FrozenClock;
use DateTimeImmutable;
use LogicException;

final class WebhookRotationTest extends TestCase
{
    private const OLD_SECRET = 'whsec_'.'b2xkLWtleTAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA=';

    private const NEW_SECRET = 'whsec_'.'bmV3LWtleTAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA=';

    private const TIMESTAMP = 1_700_000_000;

    public function test_old_and_new_signatures_verify_during_overlap_in_deterministic_order(): void
    {
        $keys = $this->overlapKeyRing();
        $signed = (new HmacWebhookSigner(keyRing: $keys))->sign(
            deliveryId: 'delivery_456',
            timestamp: self::TIMESTAMP,
            rawBody: '{}',
            secretRefs: ['old', 'new'],
        );
        $clock = new FrozenClock(new DateTimeImmutable('@'.self::TIMESTAMP));

        $this->assertCount(2, $signed->signatures);
        $this->assertTrue((new WebhookReceiverVerifier(clock: $clock, secrets: [self::OLD_SECRET]))->verify(
            headers: $signed->toArray(),
            rawBody: '{}',
        ));
        $this->assertTrue((new WebhookReceiverVerifier(clock: $clock, secrets: [self::NEW_SECRET]))->verify(
            headers: $signed->toArray(),
            rawBody: '{}',
        ));

        $reversed = (new HmacWebhookSigner(keyRing: $keys))->sign(
            deliveryId: 'delivery_456',
            timestamp: self::TIMESTAMP,
            rawBody: '{}',
            secretRefs: ['new', 'old'],
        );

        $this->assertSame($signed->signatures, $reversed->signatures);
    }

    public function test_http_success_does_not_infer_readiness_and_regular_revoke_requires_confirmation(): void
    {
        $keys = $this->overlapKeyRing();
        $signed = (new HmacWebhookSigner(keyRing: $keys))->sign(
            deliveryId: 'delivery_456',
            timestamp: self::TIMESTAMP,
            rawBody: '{}',
            secretRefs: ['old', 'new'],
        );
        $receiver = new WebhookReceiverVerifier(
            clock: new FrozenClock(new DateTimeImmutable('@'.self::TIMESTAMP)),
            secrets: [self::OLD_SECRET],
        );

        $this->assertTrue($receiver->verify(headers: $signed->toArray(), rawBody: '{}'));
        $this->assertFalse($keys->receiverReady('new'));

        try {
            $keys->revokePrevious(previousSecretRef: 'old', successorSecretRef: 'new');
            $this->fail('A 2xx-equivalent verification must not authorize rotation.');
        } catch (LogicException $exception) {
            $this->assertSame('Webhook receiver readiness is not confirmed.', $exception->getMessage());
        }

        $keys->confirmReceiverReadiness('new');
        $keys->revokePrevious(previousSecretRef: 'old', successorSecretRef: 'new');

        $this->expectException(LogicException::class);
        (new HmacWebhookSigner(keyRing: $keys))->sign(
            deliveryId: 'delivery_456',
            timestamp: self::TIMESTAMP,
            rawBody: '{}',
            secretRefs: ['old'],
        );
    }

    public function test_compromise_can_revoke_without_readiness_confirmation(): void
    {
        $keys = new WebhookKeyRing;
        $keys->provision(
            secretRef: 'old',
            secret: new SecretRef(self::OLD_SECRET),
            notBefore: self::TIMESTAMP - 600,
        );

        $keys->revokeCompromised('old');

        $this->expectException(LogicException::class);
        $keys->resolveForSigning(secretRefs: ['old'], timestamp: self::TIMESTAMP);
    }

    private function overlapKeyRing(): WebhookKeyRing
    {
        $keys = new WebhookKeyRing;
        $keys->provision(
            secretRef: 'old',
            secret: new SecretRef(self::OLD_SECRET),
            notBefore: self::TIMESTAMP - 600,
            notAfter: self::TIMESTAMP + 600,
        );
        $keys->provision(
            secretRef: 'new',
            secret: new SecretRef(self::NEW_SECRET),
            notBefore: self::TIMESTAMP - 60,
        );

        return $keys;
    }
}
