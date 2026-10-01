<?php

declare(strict_types=1);

namespace Roave\BackwardCompatibility\LocateSources;

use Roave\BackwardCompatibility\SourceLocator\ReplaceSourcePathOfLocatedSources;
use Roave\BetterReflection\Identifier\Identifier;
use Roave\BetterReflection\Identifier\IdentifierType;
use Roave\BetterReflection\Reflection\Reflection;
use Roave\BetterReflection\Reflection\ReflectionClass;
use Roave\BetterReflection\Reflector\Reflector;
use Roave\BetterReflection\SourceLocator\Ast\Locator;
use Roave\BetterReflection\SourceLocator\Type\Composer\Factory\MakeLocatorForComposerJson;
use Roave\BetterReflection\SourceLocator\Type\SourceLocator;
use RuntimeException;

/** Roave comparison selection; dependency lookup retains every source declaration. */
final class LocateSourcesViaComposerJson implements LocateSources
{
    public function __construct(private Locator $astLocator) {}

    public function __invoke(string $installationPath): SourceLocator
    {
        $locator = (new MakeLocatorForComposerJson)(
            $installationPath,
            new ReplaceSourcePathOfLocatedSources($this->astLocator, $installationPath),
        );
        $bytes = file_get_contents($installationPath.'/composer.json');

        if ($bytes === false) {
            throw new RuntimeException('Missing comparison composer.json');
        }
        $composer = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($composer)) {
            throw new RuntimeException('Invalid comparison composer.json');
        }
        // Only a historical revision's own metadata can authorize legacy membership.
        // New comparison roots deliberately have no namespace metadata.
        $extra = $composer['extra'] ?? [];
        $corex = is_array($extra) ? ($extra['corex'] ?? []) : [];
        $raw = is_array($corex) ? ($corex['public-api'] ?? []) : [];

        if (! is_array($raw)) {
            throw new RuntimeException('Invalid historical source membership');
        }
        $legacy = [];
        foreach ($raw as $prefix) {
            if (! is_string($prefix) || $prefix === '') {
                throw new RuntimeException('Invalid historical namespace prefix');
            }
            $legacy[] = $prefix;
        }

        return new class($locator, $legacy) implements SourceLocator
        {
            /** @param list<string> $legacy */
            public function __construct(private SourceLocator $locator, private array $legacy) {}

            public function locateIdentifier(Reflector $reflector, Identifier $identifier): ?Reflection
            {
                return $this->locator->locateIdentifier($reflector, $identifier);
            }

            public function locateIdentifiersByType(Reflector $reflector, IdentifierType $identifierType): array
            {
                return array_values(array_filter(
                    $this->locator->locateIdentifiersByType($reflector, $identifierType),
                    function (Reflection $symbol): bool {
                        if (! $symbol instanceof ReflectionClass) {
                            return false;
                        }

                        if ($symbol->getAttributesByName('CoreX\\Attributes\\Api') !== []
                            || $symbol->getAttributesByName('CoreX\\Attributes\\Spi') !== []) {
                            return true;
                        }
                        foreach ($this->legacy as $prefix) {
                            if ($symbol->getName() === $prefix || str_starts_with($symbol->getName(), rtrim($prefix, '\\').'\\')) {
                                return true;
                            }
                        }

                        return false;
                    },
                ));
            }
        };
    }
}
