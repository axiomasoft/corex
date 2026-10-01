<?php

declare(strict_types=1);

namespace CoreX\Settings\Contracts;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;

/**
 * ModuleManifest-declared setting defaults, injected so axiomasoft/corex never imports
 * `CoreX\Modules\Internal\Settings\DefaultSettingDefinition` (direction core←modules). The boxed default
 * is a null object ({@see CoreX\Settings\Internal\NullSettingDefaultsProvider});
 * ModulesServiceProvider (axiomasoft/corex) wires the real implementation over
 * the compiled manifest registry.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
interface SettingDefaultsProvider
{
    public function defaultFor(string $namespace, string $key): mixed;

    public function isSensitive(string $namespace, string $key): bool;
}
