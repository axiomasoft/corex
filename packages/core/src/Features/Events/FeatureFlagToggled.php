<?php

declare(strict_types=1);

namespace CoreX\Features\Events;

use CoreX\Messaging\Events\DomainEvent;
use CoreX\Settings\Data\SettingsScope;
use Override;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/**
 * Domain-event shape for a feature-flag state change
 * (`core.feature.toggled`). `FeatureFlags` ships no toggle-mutator itself —
 * `define()` only registers a manifest default — so no production code path
 * dispatches this yet; the admin toggle action that will (UI feature-flag
 * management is out of scope here) is deferred to future work.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class FeatureFlagToggled extends DomainEvent
{
    public function __construct(
        public readonly string $key,
        public readonly ?SettingsScope $scope,
        public readonly bool $isEnabled,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function name(): string
    {
        return 'core.feature.toggled';
    }
}
