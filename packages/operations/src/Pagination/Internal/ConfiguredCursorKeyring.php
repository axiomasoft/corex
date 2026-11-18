<?php

declare(strict_types=1);

namespace CoreX\Operations\Pagination\Internal;

use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Pagination\Contracts\CursorKeyring;
use Override;
use Packman\Attributes\Internal;

/** Cursor keyring backed by the `cursor` section of the API options. */
#[Internal]
final readonly class ConfiguredCursorKeyring implements CursorKeyring
{
    public function __construct(private ApiOptions $options) {}

    #[Override]
    public function currentKeyId(): string
    {
        return $this->options->cursorKeyId;
    }

    #[Override]
    public function ttlSeconds(): int
    {
        return $this->options->cursorTtlSeconds;
    }

    #[Override]
    public function verifyingSecret(string $keyId): ?string
    {
        $secret = $this->options->cursorKeyring[$keyId] ?? null;

        if ($secret === null || ! $secret->isPresent()) {
            return null;
        }

        return $secret->reveal();
    }
}
