<?php

declare(strict_types=1);

namespace Flex\Modules\Settings;

use CoreX\Contracts\SettingDefaultsProvider;
use CoreX\Settings\NullSettingDefaultsProvider;
use Flex\Modules\Compiler\CompiledSettingDefault;
use Flex\Modules\Contracts\ModuleRegistry;
use Flex\Modules\SettingDefault;
use Override;

/**
 * Real {@see SettingDefaultsProvider} for flex/modules (D20/D22/D23 —
 * direction core←modules stays intact: `corex/core` only ever sees the
 * contract, this implementation lives here and `ModulesServiceProvider`
 * rebinds it over the boxed {@see NullSettingDefaultsProvider}
 * default). Reads manifest-declared {@see SettingDefault}s off
 * the COMPILED registry ({@see ModuleRegistry::compiled()}, P1.15) — never
 * off `ModuleDiscovery::discover()`, which would re-parse every manifest's
 * `manifest()` method on this hot path (`DatabaseSettingsRepository::get()`
 * calls `defaultFor()` on every uncached read, P1.25 Code Guidance).
 */
final class CompiledSettingDefaultsProvider implements SettingDefaultsProvider
{
    /** @var array<string, array<string, CompiledSettingDefault>>|null keyed by [namespace][key], built once per instance */
    private ?array $index = null;

    public function __construct(private readonly ModuleRegistry $registry) {}

    #[Override]
    public function defaultFor(string $namespace, string $key): mixed
    {
        return $this->index()[$namespace][$key]->definition->default ?? null;
    }

    #[Override]
    public function isSensitive(string $namespace, string $key): bool
    {
        return $this->index()[$namespace][$key]->definition->sensitive ?? false;
    }

    /** @return array<string, array<string, CompiledSettingDefault>> */
    private function index(): array
    {
        if ($this->index !== null) {
            return $this->index;
        }

        $index = [];

        foreach ($this->registry->compiled()->settings as $setting) {
            $index[$setting->namespace][$setting->definition->key] = $setting;
        }

        return $this->index = $index;
    }
}
