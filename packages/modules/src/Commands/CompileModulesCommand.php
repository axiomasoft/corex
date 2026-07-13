<?php

declare(strict_types=1);

namespace Flex\Modules\Commands;

use Flex\Modules\Discovery\ModuleDiscovery;
use Flex\Modules\Exceptions\InvalidManifestException;
use Illuminate\Console\Command;

/**
 * Discovery-and-validation half of the compile pipeline (B-10 §5.2): finds
 * module manifests and rejects invalid ones. Extender application and the
 * compiled-registry write are added by P1.4/P1.5.
 */
final class CompileModulesCommand extends Command
{
    protected $signature = 'flex:modules:compile';

    protected $description = 'Discover Flex module manifests and validate them.';

    public function handle(ModuleDiscovery $discovery): int
    {
        try {
            $manifests = $discovery->discover();
        } catch (InvalidManifestException $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info(sprintf('Discovered %d module manifest(s).', count($manifests)));

        return self::SUCCESS;
    }
}
