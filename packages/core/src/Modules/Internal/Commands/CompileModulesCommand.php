<?php

declare(strict_types=1);

namespace CoreX\Modules\Internal\Commands;

use CoreX\Modules\Data\CompiledModule;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Exceptions\InvalidManifestException;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Modules\Internal\Discovery\ModuleDiscovery;
use CoreX\Modules\Internal\Registry\CompiledModuleRegistry;
use CoreX\Modules\ModulesServiceProvider;
use Illuminate\Console\Command;

/**
 * Deploy-hook compile pipeline: discovers module manifests, validates
 * them, applies the closed extender set in memory, then writes both the
 * per-module metadata and the full {@see CompiledRegistry} (all 8 slices)
 * atomically to `bootstrap/cache/corex_modules.php` (tmp+rename) for
 * {@see CompiledModuleRegistry} to read on the hot path. Wired into
 * `php artisan optimize` (deploy) by {@see ModulesServiceProvider}.
 *
 * @internal spec: B-10 §5.2, §7.4 п.7, P1.15
 */
final class CompileModulesCommand extends Command
{
    protected $signature = 'corex:modules:compile';

    protected $description = 'Discover CoreX module manifests, validate and compile their extenders.';

    public function handle(ModuleDiscovery $discovery, RegistryCompiler $compiler): int
    {
        try {
            $manifests = $discovery->discover();
        } catch (InvalidManifestException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Discovered %d module manifest(s).', count($manifests)));

        try {
            $compiled = $compiler->compile($manifests);
        } catch (CompilationException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Compiled %d entity definition(s).', count($compiled->entities)));

        $cachePath = $this->laravel->basePath(CompiledModuleRegistry::CACHE_PATH);
        CompiledModuleRegistry::writeAtomic(
            path: $cachePath,
            modules: array_map(CompiledModule::fromManifest(...), $manifests),
            registry: $compiled,
        );

        $this->components->info(sprintf('Wrote compiled registry to %s.', $cachePath));

        return self::SUCCESS;
    }
}
