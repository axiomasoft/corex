<?php

declare(strict_types=1);

namespace CoreX\Events;

use CoreX\Enums\SettingScope;
use Override;

/**
 * Fired by `SettingsRepository::set()` (B-10 §3.1) ONLY when the stored
 * value actually changes, and only after the write's transaction commits
 * (`DatabaseSettingsRepository::set()`, D38/A-audit fix P1.22) — a no-op
 * `set()` of an unchanged value does not spam listeners, and a rolled-back
 * write never fires it. `old`/`new` are masked when `sensitive` is true —
 * this event never carries a sensitive value in the clear (B-10 §7.4 п.8),
 * including to queued listeners' logs.
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
