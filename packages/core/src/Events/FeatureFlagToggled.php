<?php

declare(strict_types=1);

namespace CoreX\Events;

use CoreX\Contracts\SettingsScope;
use Override;

/**
 * Domain-event shape for a feature-flag state change (B-10 domain-events
 * table, `core.feature.toggled`). `FeatureFlags` (P1.9) ships no
 * toggle-mutator itself — `define()` only registers a manifest default —
 * so no production code path dispatches this yet; the admin toggle action
 * that will (Scope Excluded: UI фич-флагов) is deferred (P1.9 Pending Work).
 */
final class FeatureFlagToggled extends DomainEvent
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
