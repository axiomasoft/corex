<?php

declare(strict_types=1);

namespace CoreX\Settings\Contracts;

use CoreX\Settings\NullSettingDefaultsProvider;

/**
 * ModuleManifest-declared setting defaults, injected so axiomasoft/corex never imports
 * `CoreX\Modules\Settings\DefaultSettingDefinition` (direction core←modules). The boxed default
 * is a null object ({@see NullSettingDefaultsProvider});
 * ModulesServiceProvider (axiomasoft/corex) wires the real implementation over
 * the compiled manifest registry.
 */
interface SettingDefaultsProvider
{
    public function defaultFor(string $namespace, string $key): mixed;

    public function isSensitive(string $namespace, string $key): bool;
}
