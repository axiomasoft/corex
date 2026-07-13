<?php

declare(strict_types=1);

namespace CoreX\Events;

use CoreX\Enums\SettingScope;
use Override;

/**
 * Fired on every `SettingsRepository::set()` (B-10 §3.1). `old`/`new` are
 * masked when `sensitive` is true — this event never carries a sensitive
 * value in the clear (B-10 §7.4 п.8), including to queued listeners' logs.
 *
 * Retrofitted onto `DomainEvent` (P1.9, OQ-4 resolution) — was interim
 * `CoreEvent` under P1.7.
 */
final class SettingChanged extends DomainEvent
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
        parent::__construct();
        $this->old = $this->sensitive ? self::MASK : $old;
        $this->new = $this->sensitive ? self::MASK : $new;
    }

    #[Override]
    public static function name(): string
    {
        return 'core.setting.changed';
    }
}
