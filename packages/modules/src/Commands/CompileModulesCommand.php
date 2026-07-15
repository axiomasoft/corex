<?php

declare(strict_types=1);

namespace Flex\Modules\Commands;

use Flex\Modules\Compiler\RegistryCompiler;
use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Exceptions\CompilationException;
use Flex\Modules\Exceptions\InvalidManifestException;
use Flex\Modules\ModulesServiceProvider;
use Flex\Modules\Registry\CompiledModule;
use Flex\Modules\Registry\CompiledModuleRegistry;
use Illuminate\Console\Command;

/**
 * Deploy-hook compile pipeline (B-10 §5.2): discovers module manifests,
 * validates them, applies the closed extender set in memory, then writes
 * both the per-module metadata and the full {@see CompiledRegistry} (all 8
 * slices — P1.15) atomically to `bootstrap/cache/flex_modules.php`
 * (tmp+rename — B-10 §7.4 п.7) for {@see CompiledModuleRegistry} to read on
 * the hot path. Wired into `php artisan optimize` (deploy) by
 * {@see ModulesServiceProvider}.
 */
final class CompileModulesCommand extends Command
{
    protected $signature = 'flex:modules:compile';

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
