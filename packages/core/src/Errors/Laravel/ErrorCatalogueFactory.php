<?php

declare(strict_types=1);

namespace CoreX\Errors\Laravel;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemDefinitionProvider;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Errors\Laravel\ProblemDefinitions;
use Packman\Modules\ModulesServiceProvider;
use Packman\PackmanServiceProvider;
use RuntimeException;

/** Builds a domain error catalogue through the public Packman registration boundary. */
#[Internal]
final class ErrorCatalogueFactory
{
    public function make(string $repository): ProblemRegistry
    {
        $previous = Container::getInstance();

        try {
            $app = new Application($repository);
            $app->instance('config', new Repository);
            $app->register(PackmanServiceProvider::class);
            $app->register(ModulesServiceProvider::class);
            $paths = glob($repository.'/packages/*/src/Errors/Internal/PackageProblemDefinitions.php');

            if ($paths === false) {
                throw new RuntimeException('Cannot enumerate error contributions.');
            }
            foreach ($paths as $path) {
                $manifestPath = dirname($path, 4).'/composer.json';
                $manifest = json_decode((string) file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
                $autoload = is_array($manifest) ? ($manifest['autoload'] ?? null) : null;
                $prefixes = is_array($autoload) ? ($autoload['psr-4'] ?? null) : null;

                if (! is_array($manifest) || ! is_string($manifest['name'] ?? null) || ! is_array($prefixes)) {
                    throw new RuntimeException('Invalid error owner manifest.');
                }
                foreach ($prefixes as $prefix => $directory) {
                    if (! is_string($prefix)) {
                        throw new RuntimeException('Invalid error namespace.');
                    }
                    $class = $prefix.'Errors\\Internal\\PackageProblemDefinitions';

                    if (! is_a($class, ProblemDefinitionProvider::class, true)) {
                        continue;
                    }
                    ProblemDefinitions::register($app, $manifestPath, $class);
                }
            }

            return $app->make(ProblemRegistry::class);
        } finally {
            Container::setInstance($previous);
        }
    }
}
