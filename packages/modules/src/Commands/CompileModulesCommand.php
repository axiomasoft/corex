<?php

declare(strict_types=1);

namespace Flex\Modules\Commands;

use Flex\Modules\Compiler\RegistryCompiler;
use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Exceptions\CompilationException;
use Flex\Modules\Exceptions\InvalidManifestException;
use Illuminate\Console\Command;

/**
 * Deploy-hook compile pipeline (B-10 §5.2): discovers module manifests,
 * validates them, applies the closed extender set in memory. The atomic
 * write of the compiled registry to `bootstrap/cache/flex_modules.php` is
 * added by P1.5.
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

        return self::SUCCESS;
    }
}
