<?php

declare(strict_types=1);

namespace CoreX\Modules\Commands;

use CoreX\Modules\Compiler\RegistryCompiler;
use CoreX\Modules\Discovery\ModuleDiscovery;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Exceptions\InvalidManifestException;
use CoreX\Modules\ModulesServiceProvider;
use CoreX\Modules\Registry\CompiledModule;
use CoreX\Modules\Registry\CompiledModuleRegistry;
use Illuminate\Console\Command;

/**
 * Deploy-hook compile pipeline (B-10 §5.2): discovers module manifests,
 * validates them, applies the closed extender set in memory, then writes
 * both the per-module metadata and the full {@see CompiledRegistry} (all 8
 * slices — P1.15) atomically to `bootstrap/cache/corex_modules.php`
 * (tmp+rename — B-10 §7.4 п.7) for {@see CompiledModuleRegistry} to read on
 * the hot path. Wired into `php artisan optimize` (deploy) by
 * {@see ModulesServiceProvider}.
 */
final class CompileModulesCommand extends Command
{
    protected $signature = 'corex:modules:compile';

    protected $description = 'Discover Flex module manifests, validate and compile their extenders.';

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

        $cachePath = CompiledModuleRegistry::defaultCachePath();
        CompiledModuleRegistry::writeAtomic(
            $cachePath,
            array_map(CompiledModule::fromManifest(...), $manifests),
            $compiled,
        );

        $this->components->info(sprintf('Wrote compiled registry to %s.', $cachePath));

        return self::SUCCESS;
    }
}
