<?php

declare(strict_types=1);

namespace CoreX\Events;

use CoreX\Enums\SettingScope;

/**
 * Fired on every `SettingsRepository::set()` (B-10 §3.1). `old`/`new` are
 * masked when `sensitive` is true — this event never carries a sensitive
 * value in the clear (B-10 §7.4 п.8), including to queued listeners' logs.
 *
 * Extends CoreEvent as an interim base: DomainEvent (P1.9) is not built yet
 * (Code Guidance P1.7) — retrofit to DomainEvent lands with P1.9 (OQ-4 pattern).
 */
final class SettingChanged extends CoreEvent
{
    private const MASK = '••••••';

    public readonly mixed $old;

    public readonly mixed $new;

    public function __construct(
        public readonly string $namespace,
        public readonly string $key,
        public readonly SettingScope $scopeType,
        public readonly ?string $scopeId,
        mixed $old,
        mixed $new,
        public readonly bool $sensitive,
    ) {
        $this->old = $this->sensitive ? self::MASK : $old;
        $this->new = $this->sensitive ? self::MASK : $new;
    }
}
