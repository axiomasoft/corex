<?php

declare(strict_types=1);

namespace CoreX\Settings\Events;

use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Messaging\Events\DomainEvent;
use CoreX\Settings\Enums\SettingScope;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Fired by `SettingsRepository::set()` ONLY when the stored value actually
 * changes, and only after the write's transaction commits
 * (`DatabaseSettingsRepository::set()`) — a no-op `set()` of an unchanged
 * value does not spam listeners, and a rolled-back write never fires it.
 * `old`/`new` are masked when `sensitive` is true — this event never
 * carries a sensitive value in the clear, including to queued listeners'
 * logs.
 *
 * Retrofitted onto `DomainEvent` — was interim `CoreEvent` before that.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class SettingChanged extends DomainEvent
{
    private const string MASK = '••••••';

    /** Immutable JSON value. */
    public readonly JsonDocument $old;

    /** Immutable JSON value. */
    public readonly JsonDocument $new;

    public function __construct(
        public readonly string $namespace,
        public readonly string $key,
        public readonly SettingScope $scopeType,
        public readonly ?string $scopeId,
        JsonDocument $old,
        JsonDocument $new,
        public readonly bool $sensitive,
    ) {
        parent::__construct();
        $this->old = $this->sensitive ? JsonDocuments::capture(self::MASK) : $old;
        $this->new = $this->sensitive ? JsonDocuments::capture(self::MASK) : $new;
    }

    #[Override]
    public static function name(): string
    {
        return 'core.setting.changed';
    }
}
