<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Tests\Unit;

use CoreX\Config\Data\SecretRef;
use CoreX\Webhooks\Internal\WebhookKeyRing;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WebhookKeyRingBoundaryTest extends TestCase
{
    public function test_two_slots_are_ordered_and_revoke_frees_a_slot_without_reviving_an_old_key(): void
    {
        $keys = new WebhookKeyRing;
        $old = new SecretRef(value: 'test-old-key');
        $new = new SecretRef(value: 'test-new-key');
        $keys->provision(secretRef: 'old', secret: $old, notBefore: 0);
        $keys->provision(secretRef: 'new', secret: $new, notBefore: 0);
        self::assertSame(['new' => $new, 'old' => $old], $keys->resolveForSigning(secretRefs: ['old', 'new'], timestamp: 0));
        self::assertFalse($keys->receiverReady(secretRef: 'unknown'));
        self::assertFalse($keys->receiverReady(secretRef: 'old'));

        try {
            $keys->provision(secretRef: 'third', secret: new SecretRef(value: 'test-third'), notBefore: 0);
            self::fail('A third unrevoked rotation slot was admitted.');
        } catch (LogicException $exception) {
            self::assertSame('Webhook key ring already has two active rotation slots.', $exception->getMessage());
        }

        try {
            $keys->revokePrevious(previousSecretRef: 'old', successorSecretRef: 'new');
            self::fail('Readiness was inferred without confirmation.');
        } catch (LogicException $exception) {
            self::assertSame('Webhook receiver readiness is not confirmed.', $exception->getMessage());
        }
        $keys->confirmReceiverReadiness(secretRef: 'new');
        self::assertTrue($keys->receiverReady(secretRef: 'new'));
        $keys->confirmReceiverReadiness(secretRef: 'old');
        $keys->revokePrevious(previousSecretRef: 'old', successorSecretRef: 'new');
        self::assertFalse($keys->receiverReady(secretRef: 'old'));
        $keys->provision(secretRef: 'third', secret: new SecretRef(value: 'test-third'), notBefore: 0);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Webhook signing key is unavailable.');
        $keys->resolveForSigning(secretRefs: ['old'], timestamp: 0);
    }

    public function test_key_activation_window_is_closed_at_its_end_and_duplicate_provisioning_is_refused(): void
    {
        $keys = new WebhookKeyRing;
        $secret = new SecretRef(value: 'test-key');
        $keys->provision(secretRef: 'key', secret: $secret, notBefore: 10, notAfter: 11);
        self::assertSame(['key' => $secret], $keys->resolveForSigning(secretRefs: ['key'], timestamp: 10));
        foreach ([9, 11] as $instant) {
            try {
                $keys->resolveForSigning(secretRefs: ['key'], timestamp: $instant);
                self::fail('Signing outside the activation window was admitted.');
            } catch (LogicException $exception) {
                self::assertSame('Webhook signing key is outside its activation window.', $exception->getMessage());
            }
        }
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Webhook key is already provisioned.');
        $keys->provision(secretRef: 'key', secret: $secret, notBefore: 10, notAfter: 12);
    }

    #[DataProvider('invalidProvisioning')]
    public function test_invalid_key_material_and_activation_ranges_are_rejected(string $ref, string $secret, int $start, ?int $end, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        (new WebhookKeyRing)->provision(secretRef: $ref, secret: new SecretRef(value: $secret), notBefore: $start, notAfter: $end);
    }

    /** @return iterable<array{string, string, int, ?int, string}> */
    public static function invalidProvisioning(): iterable
    {
        foreach (['', 'Key', str_repeat('a', 65), "key\n", '.key'] as $ref) {
            yield [$ref, 'test-key', 0, null, 'Webhook key ref is invalid.'];
        }
        yield ['key', '', 0, null, 'Webhook secret is not configured.'];
        yield ['key', 'test-key', -1, null, 'Webhook key activation window is invalid.'];
        yield ['key', 'test-key', 10, 10, 'Webhook key activation window is invalid.'];
        yield ['key', 'test-key', 10, 9, 'Webhook key activation window is invalid.'];
    }

    #[DataProvider('invalidSigningSets')]
    public function test_signing_never_accepts_empty_duplicate_or_untyped_key_sets(array $refs, string $message): void
    {
        $keys = new WebhookKeyRing;
        $keys->provision(secretRef: 'key', secret: new SecretRef(value: 'test-key'), notBefore: 0);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $keys->resolveForSigning(secretRefs: $refs, timestamp: 0);
    }

    /** @return iterable<array{array<mixed>, string}> */
    public static function invalidSigningSets(): iterable
    {
        yield [[], 'Webhook signing requires one or two key refs.'];
        yield [['key', 'next', 'third'], 'Webhook signing requires one or two key refs.'];
        yield [[7], 'Webhook signing key ref is invalid.'];
        yield [['key', 'key'], 'Webhook signing key refs must be unique.'];
    }

    public function test_compromise_and_readiness_never_restore_a_revoked_entry(): void
    {
        $keys = new WebhookKeyRing;
        $ref = str_repeat('a', 64);
        $keys->provision(secretRef: $ref, secret: new SecretRef(value: 'test-key'), notBefore: 0);
        $keys->confirmReceiverReadiness(secretRef: $ref);
        $keys->revokeCompromised(secretRef: $ref);
        self::assertFalse($keys->receiverReady(secretRef: $ref));

        try {
            $keys->revokePrevious(previousSecretRef: $ref, successorSecretRef: $ref);
            self::fail('A key was admitted as its own successor.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Webhook rotation requires distinct keys.', $exception->getMessage());
        }
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Webhook signing key is unavailable.');
        $keys->confirmReceiverReadiness(secretRef: $ref);
    }
}
