<?php

declare(strict_types=1);

namespace Flex\Modules\Compiler;

use Flex\Modules\Exceptions\CompilationException;
use Flex\Modules\Extend\Extender;
use Flex\Modules\Manifest;

/**
 * Compile-time checks (B-10 §5.2/§7.4 п.4) — every failure here is a deploy
 * failure, never a warning. Called by RegistryCompiler before/while
 * building the CompiledRegistry.
 */
final class CompilationValidator
{
    /**
     * @param  list<Manifest>  $manifests
     */
    public function validateRequiredFields(array $manifests): void
    {
        foreach ($manifests as $manifest) {
            if ($manifest->title === null) {
                throw CompilationException::missingTitle($manifest->composerName);
            }

            if ($manifest->tablePrefix === null) {
                throw CompilationException::missingTablePrefix($manifest->composerName);
            }
        }
    }

    /** @param  list<Manifest>  $manifests */
    public function validateTablePrefixes(array $manifests): void
    {
        /** @var array<string, string> $ownerByPrefix */
        $ownerByPrefix = [];

        foreach ($manifests as $manifest) {
            if ($manifest->tablePrefix === null) {
                continue;
            }

            $owner = $ownerByPrefix[$manifest->tablePrefix] ?? null;

            if ($owner !== null) {
                throw CompilationException::duplicateTablePrefix($manifest->tablePrefix, $owner, $manifest->composerName);
            }

            $ownerByPrefix[$manifest->tablePrefix] = $manifest->composerName;
        }
    }

    /**
     * @param  list<Manifest>  $manifests
     * @param  list<string>  $installedPackages  composer names known installed beyond $manifests (e.g. corex/core).
     */
    public function validateRequires(array $manifests, array $installedPackages): void
    {
        $installed = [...$installedPackages, ...array_map(
            static fn (Manifest $manifest): string => $manifest->composerName,
            $manifests,
        )];

        foreach ($manifests as $manifest) {
            foreach ($manifest->requires as $dep) {
                if ($dep->type !== 'module') {
                    continue;
                }

                if (! in_array($dep->target, $installed, true)) {
                    throw CompilationException::unmetRequirement($manifest->composerName, $dep->target);
                }
            }
        }
    }

    /** @param  list<Manifest>  $manifests */
    public function validateExtenderSet(array $manifests): void
    {
        foreach ($manifests as $manifest) {
            foreach ($manifest->extenders as $extender) {
                if (! $this->isKnownExtender($extender)) {
                    throw CompilationException::unknownExtender($manifest->composerName, $extender::class);
                }
            }
        }
    }

    public function validateEntityHandleKnown(string $declaringComposerName, string $entityHandle, bool $known): void
    {
        if (! $known) {
            throw CompilationException::unknownEntityHandle($declaringComposerName, $entityHandle);
        }
    }

    /**
     * `components()` → `#[AsComponent]` (B-10 §5.2) is a deliberate no-op:
     * corex/components is deferred out of P1 (D6). Kept as a named hook so
     * the check has an obvious home once components land.
     *
     * @param  list<Manifest>  $manifests
     */
    public function validateComponents(array $manifests): void
    {
        // Deferred (D6) — corex/components has no consumer in P1.
    }

    private function isKnownExtender(Extender $extender): bool
    {
        foreach (PipelineStages::ORDER as $class) {
            if ($extender instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
