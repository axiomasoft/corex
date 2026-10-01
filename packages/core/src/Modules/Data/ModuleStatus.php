<?php

declare(strict_types=1);

namespace CoreX\Modules\Data;

use CoreX\Attributes\Api;
use CoreX\Modules\Enums\ModuleState;

/**
 * Point-in-time snapshot returned by {@see Contracts\ModuleLifecycle::status()}.
 * `pendingUpgrade` is derived (codeVersion > dbVersion), never stored — a
 * lagging schema is a normal state between deploy and the control-plane's
 * upgrade wave.
 */
#[Api(since: '1.0')]
final readonly class ModuleStatus
{
    public function __construct(
        public string $name,
        public ModuleState $state,
        public string $codeVersion,
        public ?string $dbVersion,
        public bool $pendingUpgrade,
        public string $edition,
    ) {}
}
