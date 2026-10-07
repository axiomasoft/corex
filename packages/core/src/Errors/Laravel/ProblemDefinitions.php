<?php

declare(strict_types=1);

namespace CoreX\Errors\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Contracts\ProblemRegistry;
use CoreX\Errors\Enums\CoreErrorCode;
use CoreX\Errors\Exceptions\ProblemRegistryException;
use CoreX\Errors\Internal\ProblemContribution;
use Illuminate\Contracts\Foundation\Application;
use ReflectionClass;

/** Register during the owning service provider's register(), before the catalogue freezes at boot. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class ProblemDefinitions
{
    /** @param class-string<ProblemDefinitionProvider> $provider */
    public static function register(Application $app, string $composerManifest, string $provider): void
    {
        $binding = ProblemContribution::class.':'.$provider;

        if ($app->bound($binding)) {
            return;
        }

        if ($app->resolved(ProblemRegistry::class)) {
            throw new ProblemRegistryException(errorCode: CoreErrorCode::RegistryFrozen, diagnostic: 'ERROR-REGISTRY-FROZEN');
        }
        $manifest = json_decode((string) file_get_contents($composerManifest), true, flags: JSON_THROW_ON_ERROR);
        $package = is_array($manifest) ? ($manifest['name'] ?? null) : null;
        $autoload = is_array($manifest) ? ($manifest['autoload'] ?? []) : [];
        $prefixes = is_array($autoload) ? ($autoload['psr-4'] ?? []) : [];
        $owner = $package === 'axiomasoft/corex' ? 'core' : (is_string($package) ? preg_replace('~^[^/]+/corex-~', '', $package) : null);
        $owned = false;
        $sourceRoots = [];
        foreach (is_array($prefixes) ? $prefixes : [] as $prefix => $paths) {
            if (! is_string($prefix) || ! str_starts_with($provider, $prefix)) {
                continue;
            }
            $file = new ReflectionClass($provider)->getFileName();
            foreach ((array) $paths as $path) {
                $directory = is_string($path) ? realpath(dirname($composerManifest).'/'.$path) : false;

                if ($directory !== false) {
                    $sourceRoots[] = $directory;
                }
                $owned = $owned || ($file !== false && $directory !== false && str_starts_with($file, $directory.DIRECTORY_SEPARATOR));
            }
        }

        if (! is_string($owner) || ! is_string($package) || preg_match('/\A[a-z][a-z0-9]*(?:-[a-z0-9]+)*\z/D', $owner) !== 1 || ! $owned) {
            throw new ProblemRegistryException(errorCode: CoreErrorCode::ForeignPrefix, diagnostic: 'ERROR-FOREIGN-OWNER');
        }
        $app->singleton($binding, static function (Application $app) use ($owner, $provider, $package, $sourceRoots): ProblemContribution {
            $instance = $app->make($provider);

            if (! $instance instanceof ProblemDefinitionProvider) {
                throw new ProblemRegistryException(errorCode: CoreErrorCode::InvalidDefinition, diagnostic: 'ERROR-INVALID-PROVIDER');
            }

            return new ProblemContribution(owner: $owner, provider: $instance, package: $package, sourceRoots: $sourceRoots);
        });
        $app->tag([$binding], ProblemDefinitionProvider::class);
    }
}
