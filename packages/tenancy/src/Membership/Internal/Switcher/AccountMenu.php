<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Internal\Switcher;

use CoreX\Tenancy\Membership\Data\AvailableAccounts;
use Packman\Attributes\Internal;

/** @internal An unavailable read deliberately exposes an empty menu. */
#[Internal]
final readonly class AccountMenu
{
    private function __construct(public bool $available, public ?AvailableAccounts $result) {}

    public static function available(AvailableAccounts $result): self
    {
        return new self(available: true, result: $result);
    }

    public static function unavailable(): self
    {
        return new self(available: false, result: null);
    }
}
