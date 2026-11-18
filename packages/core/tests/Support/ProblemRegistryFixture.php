<?php

declare(strict_types=1);

namespace CoreX\Testing;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Packman\Errors\Contracts\ProblemDefinitionProvider;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Errors\Laravel\ProblemDefinitions;
use Packman\Errors\Laravel\ProblemRegistryFactory;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Filesystem\Path;

/** Builds isolated catalogues through the owner's public registration boundary. */
final class ProblemRegistryFixture
{
    public static function compile(string $owner, ProblemDefinitionProvider $provider): ProblemRegistry
    {
        $manifest = tempnam(sys_get_temp_dir(), 'corex-problem-fixture-');
        $source = new ReflectionClass($provider)->getFileName();

        if ($manifest === false || $source === false) {
            throw new RuntimeException('Cannot create isolated problem registry fixture.');
        }

        $previous = Container::getInstance();

        try {
            file_put_contents($manifest, json_encode([
                'name' => 'fixture/'.$owner,
                'extra' => ['packman' => ['error-prefix' => $owner]],
                'autoload' => ['psr-4' => ['' => [
                    Path::makeRelative(dirname($source), dirname($manifest)),
                    Path::makeRelative(dirname(__DIR__, 2).'/src', dirname($manifest)),
                ]]],
            ], JSON_THROW_ON_ERROR));
            $app = new Application;
            $app->instance('config', new Repository);
            $app->instance($provider::class, $provider);
            ProblemDefinitions::register($app, $manifest, $provider::class);

            return (new ProblemRegistryFactory)->make($app);
        } finally {
            Container::setInstance($previous);
            unlink($manifest);
        }
    }
}
