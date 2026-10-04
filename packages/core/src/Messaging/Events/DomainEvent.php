<?php

declare(strict_types=1);

namespace CoreX\Messaging\Events;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Data\TenantContext;
use CoreX\Messaging\Internal\DomainEventDefaults;
use DateTimeImmutable;
use Illuminate\Support\Str;

/**
 * Canonical base for ecosystem domain events (eventId/occurredAt/tenant/
 * `name()`). Supersedes the empty `CoreEvent` marker —
 * `Cancellable` is a SEPARATE optional interface for pre-action events that
 * can abort the operation ({@see Cancellable}), not mixed into this base:
 * most domain events (SettingChanged, FeatureFlagToggled, …) are purely
 * informational.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
abstract readonly class DomainEvent
{
    public readonly string $eventId;

    public readonly DateTimeImmutable $occurredAt;

    public readonly TenantContext $tenant;

    public function __construct(?TenantContext $tenant = null)
    {
        $this->eventId = (string) Str::uuid7();
        $this->occurredAt = DomainEventDefaults::now();
        $this->tenant = $tenant ?? DomainEventDefaults::tenant();
    }

    /**
     * Stable dotted name, e.g. `core.setting.changed`.
     */
    abstract public static function name(): string;
}
