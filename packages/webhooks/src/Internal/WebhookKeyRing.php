<?php

declare(strict_types=1);

namespace CoreX\Webhooks\Internal;

use CoreX\Config\SecretRef;
use InvalidArgumentException;
use LogicException;
use SensitiveParameter;

final class WebhookKeyRing
{
    /**
     * @var array<string, array{secret: SecretRef, notBefore: int, notAfter: int|null, receiverReady: bool, revoked: bool}>
     */
    private array $keys = [];

    public function provision(
        string $secretRef,
        #[SensitiveParameter] SecretRef $secret,
        int $notBefore,
        ?int $notAfter = null,
    ): void {
        $this->assertSecretRef($secretRef);

        if (! $secret->isPresent()) {
            throw new InvalidArgumentException('Webhook secret is not configured.');
        }

        if ($notBefore < 0 || ($notAfter !== null && $notAfter <= $notBefore)) {
            throw new InvalidArgumentException('Webhook key activation window is invalid.');
        }

        if (isset($this->keys[$secretRef])) {
            throw new LogicException('Webhook key is already provisioned.');
        }

        if ($this->unrevokedCount() >= 2) {
            throw new LogicException('Webhook key ring already has two active rotation slots.');
        }

        $this->keys[$secretRef] = [
            'secret' => $secret,
            'notBefore' => $notBefore,
            'notAfter' => $notAfter,
            'receiverReady' => false,
            'revoked' => false,
        ];
    }

    public function confirmReceiverReadiness(string $secretRef): void
    {
        $this->assertUsableEntry($secretRef);

        $this->keys[$secretRef]['receiverReady'] = true;
    }

    public function receiverReady(string $secretRef): bool
    {
        return isset($this->keys[$secretRef])
            && ! $this->keys[$secretRef]['revoked']
            && $this->keys[$secretRef]['receiverReady'];
    }

    public function revokePrevious(
        string $previousSecretRef,
        string $successorSecretRef,
    ): void {
        if ($previousSecretRef === $successorSecretRef) {
            throw new InvalidArgumentException('Webhook rotation requires distinct keys.');
        }

        $this->assertUsableEntry($previousSecretRef);
        $this->assertUsableEntry($successorSecretRef);

        if (! $this->receiverReady($successorSecretRef)) {
            throw new LogicException('Webhook receiver readiness is not confirmed.');
        }

        $this->keys[$previousSecretRef]['revoked'] = true;
    }

    public function revokeCompromised(string $secretRef): void
    {
        $this->assertUsableEntry($secretRef);

        $this->keys[$secretRef]['revoked'] = true;
    }

    /**
     * @param  array<mixed>  $secretRefs
     * @return array<string, SecretRef>
     */
    public function resolveForSigning(array $secretRefs, int $timestamp): array
    {
        if (count($secretRefs) < 1 || count($secretRefs) > 2) {
            throw new InvalidArgumentException('Webhook signing requires one or two key refs.');
        }

        foreach ($secretRefs as $secretRef) {
            if (! is_string($secretRef)) {
                throw new InvalidArgumentException('Webhook signing key ref is invalid.');
            }
        }

        if (count(array_unique($secretRefs)) !== count($secretRefs)) {
            throw new InvalidArgumentException('Webhook signing key refs must be unique.');
        }

        sort($secretRefs, SORT_STRING);

        $resolved = [];

        foreach ($secretRefs as $secretRef) {
            $this->assertUsableEntry($secretRef);
            $key = $this->keys[$secretRef];

            if ($timestamp < $key['notBefore'] || ($key['notAfter'] !== null && $timestamp >= $key['notAfter'])) {
                throw new LogicException('Webhook signing key is outside its activation window.');
            }

            $resolved[$secretRef] = $key['secret'];
        }

        return $resolved;
    }

    private function assertSecretRef(string $secretRef): void
    {
        if (preg_match('/\A[a-z][a-z0-9._-]{0,63}\z/D', $secretRef) !== 1) {
            throw new InvalidArgumentException('Webhook key ref is invalid.');
        }
    }

    private function assertUsableEntry(string $secretRef): void
    {
        $this->assertSecretRef($secretRef);

        if (! isset($this->keys[$secretRef]) || $this->keys[$secretRef]['revoked']) {
            throw new LogicException('Webhook signing key is unavailable.');
        }
    }

    private function unrevokedCount(): int
    {
        return count(array_filter(
            array: $this->keys,
            callback: fn (array $key): bool => ! $key['revoked'],
        ));
    }
}
