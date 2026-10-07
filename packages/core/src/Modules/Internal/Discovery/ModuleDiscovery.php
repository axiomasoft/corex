<?php

declare(strict_types=1);

namespace CoreX\Modules\Internal\Discovery;

use CoreX\Modules\Contracts\ModuleDefinition;
use CoreX\Modules\Data\ModuleManifest;
use CoreX\Modules\Exceptions\InvalidManifestException;

/**
 * Finds installed modules and builds their manifests. Default source is
 * `vendor/composer/installed.json` (`type: corex-module`, class from
 * `extra.corex.module`); an injected fallback list lets tests and
 * testbench avoid depending on real installed.json contents.
 *
 * @internal spec: B-10 §3.2/§5.2
 */
final readonly class ModuleDiscovery implements \CoreX\Modules\Contracts\ModuleDiscovery
{
    /**
     * @param  list<class-string<ModuleDefinition>>  $fallbackModules  injectable seam for tests/testbench.
     */
    public function __construct(
        private array $fallbackModules = [],
        private ?string $installedJsonPath = null,
    ) {}

    /** @return list<ModuleManifest> */
    public function discover(): array
    {
        $classes = $this->fallbackModules !== [] ? $this->fallbackModules : $this->scanInstalledJson();

        return array_map($this->manifestFor(...), $classes);
    }

    private function manifestFor(string $class): ModuleManifest
    {
        if (! is_subclass_of($class, ModuleDefinition::class)) {
            throw InvalidManifestException::notAModuleDefinition($class);
        }

        /** @var ModuleDefinition $module */
        $module = new $class;

        return $module->manifest();
    }

    /** @return list<string> */
    private function scanInstalledJson(): array
    {
        $path = $this->installedJsonPath;

        if ($path === null || ! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            return [];
        }

        /** @var list<mixed> $packages */
        $packages = is_array($data['packages'] ?? null) ? $data['packages'] : $data;

        $classes = [];

        foreach ($packages as $package) {
            if (! is_array($package) || ($package['type'] ?? null) !== 'corex-module') {
                continue;
            }

            $extra = $package['extra'] ?? null;
            $corex = is_array($extra) ? ($extra['corex'] ?? null) : null;
            $class = is_array($corex) ? ($corex['module'] ?? null) : null;

            if (is_string($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
