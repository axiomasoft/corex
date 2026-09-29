<?php

declare(strict_types=1);

namespace CoreX\Modules\Compiler;

use CoreX\Modules\Definition\ModuleManifest;
use CoreX\Modules\Exceptions\CompilationException;
use CoreX\Modules\Extend\Extender;

/**
 * Compile-time checks — every failure here is a deploy failure, never a
 * warning. Called by RegistryCompiler before/while building the
 * CompiledRegistry.
 *
 * @internal spec: B-10 §5.2/§7.4 п.4
 */
final class CompilationValidator
{
    /** @param list<ModuleManifest> $manifests */
    public function validateDependencyGraph(array $manifests): void
    {
        /** @var array<string, list<string>> $graph */
        $graph = [];

        foreach ($manifests as $manifest) {
            $graph[$manifest->composerName] = array_values(array_map(
                static fn ($dependency): string => $dependency->package,
                array_filter($manifest->requires, static fn ($dependency): bool => $dependency->kind === 'module'),
            ));
        }

        /** @var array<string, true> $visited */
        $visited = [];
        $visit = function (string $module, array $path) use (&$visit, &$visited, $graph): void {
            if (in_array($module, $path, true)) {
                throw new CompilationException('Cyclic module dependency: '.implode(' -> ', [...$path, $module]));
            }

            if (isset($visited[$module])) {
                return;
            }

            foreach ($graph[$module] ?? [] as $dependency) {
                $visit($dependency, [...$path, $module]);
            }

            $visited[$module] = true;
        };

        foreach (array_keys($graph) as $module) {
            $visit($module, []);
        }
    }

    /**
     * @param  list<ModuleManifest>  $manifests
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

    /** @param list<ModuleManifest> $manifests */
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
     * @param  list<ModuleManifest>  $manifests
     * @param  list<string>  $installedPackages  composer names known installed beyond $manifests (e.g. axiomasoft/corex).
     */
    public function validateRequires(array $manifests, array $installedPackages): void
    {
        $installed = [...$installedPackages, ...array_map(
            static fn (ModuleManifest $manifest): string => $manifest->composerName,
            $manifests,
        )];

        foreach ($manifests as $manifest) {
            foreach ($manifest->requires as $dep) {
                if ($dep->kind !== 'module') {
                    continue;
                }

                if (! in_array($dep->package, $installed, true)) {
                    throw CompilationException::unmetRequirement($manifest->composerName, $dep->package);
                }
            }
        }
    }

    /** @param list<ModuleManifest> $manifests */
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
     * `components()` → `#[AsComponent]` is a deliberate no-op:
     * corex/components is deferred, with no consumer yet. Kept as a named
     * hook so the check has an obvious home once components land.
     *
     * @param  list<ModuleManifest>  $manifests
     *
     * @internal spec: B-10 §5.2, D6
     */
    public function validateComponents(array $manifests): void
    {
        // Deferred — corex/components has no consumer yet.
    }

    /**
     * @param  list<NormalizedExtension>  $records
     */
    public function validateNormalizedIdentities(array $records): void
    {
        /** @var array<string, NormalizedExtension> $seen */
        $seen = [];

        foreach ($records as $record) {
            $previous = $seen[$record->key()] ?? null;

            if ($previous !== null) {
                throw CompilationException::duplicateIdentity(
                    $record->point,
                    $record->id,
                    $previous->owner,
                    $record->owner,
                );
            }

            $seen[$record->key()] = $record;
        }
    }

    /**
     * @param  list<NormalizedExtension>  $records
     * @param  list<ModuleManifest>  $manifests
     * @return array{effective: list<NormalizedExtension>, replacements: list<array{point: string, target: string, replacement: string, owner: string}>}
     */
    public function applyReplacements(array $records, array $manifests): array
    {
        /** @var array<string, NormalizedExtension> $byKey */
        $byKey = [];

        foreach ($records as $record) {
            $byKey[$record->key()] = $record;
        }

        /** @var list<array{point: string, target: string, replacement: string, owner: string}> $provenance */
        $provenance = [];
        /** @var array<string, string> $incomingOwner */
        $incomingOwner = [];
        /** @var array<string, list<string>> $graph */
        $graph = [];

        foreach ($manifests as $manifest) {
            foreach ($manifest->replacements as $replacement) {
                $targetKey = $replacement->point."\0".$replacement->targetId;
                $replacementKey = $replacement->point."\0".$replacement->replacementId;
                $target = $byKey[$targetKey] ?? null;
                $incoming = $byKey[$replacementKey] ?? null;

                if ($target === null) {
                    throw CompilationException::replacementTargetMissing(
                        $replacement->point,
                        $replacement->targetId,
                        $manifest->composerName,
                    );
                }

                if ($incoming === null) {
                    throw CompilationException::replacementMissing(
                        $replacement->point,
                        $replacement->replacementId,
                        $manifest->composerName,
                    );
                }

                if ($incoming->owner !== $manifest->composerName) {
                    throw CompilationException::replacementForeignOwner(
                        $replacement->point,
                        $replacement->replacementId,
                        $manifest->composerName,
                        $incoming->owner,
                    );
                }

                $claimed = $incomingOwner[$targetKey] ?? null;

                if ($claimed !== null) {
                    throw CompilationException::replacementDuplicateIncoming(
                        $replacement->point,
                        $replacement->targetId,
                        $claimed,
                        $manifest->composerName,
                    );
                }

                $incomingOwner[$targetKey] = $manifest->composerName;
                $graph[$replacementKey][] = $targetKey;
                $provenance[] = [
                    'point' => $replacement->point,
                    'target' => $replacement->targetId,
                    'replacement' => $replacement->replacementId,
                    'owner' => $manifest->composerName,
                ];
            }
        }

        $this->assertAcyclic($graph, $byKey);

        $effective = array_values(array_filter(
            $records,
            static fn (NormalizedExtension $record): bool => ! isset($incomingOwner[$record->key()]),
        ));

        return [
            'effective' => $effective,
            'replacements' => $provenance,
        ];
    }

    /**
     * @param  list<NormalizedExtension>  $records
     */
    public function validateMenuOrder(array $records): void
    {
        /** @var array<string, NormalizedExtension> $menus */
        $menus = [];

        foreach ($records as $record) {
            if ($record->point === 'menu') {
                $menus[$record->id] = $record;
            }
        }

        foreach ($menus as $record) {
            if ($record->after === null) {
                continue;
            }

            $panel = explode(':', $record->id, 2)[0];
            $afterId = $panel.':'.$record->after;

            if (! isset($menus[$afterId])) {
                throw CompilationException::menuAfterMissing($record->id, $record->after, $record->owner);
            }
        }

        /** @var array<string, list<string>> $graph */
        $graph = [];

        foreach ($menus as $record) {
            if ($record->after === null) {
                continue;
            }

            $panel = explode(':', $record->id, 2)[0];
            $graph[$record->id][] = $panel.':'.$record->after;
        }

        $this->assertAcyclic($graph, $menus, menu: true);
    }

    /**
     * @param  array<string, list<string>>  $graph
     * @param  array<string, NormalizedExtension>  $nodes
     */
    private function assertAcyclic(array $graph, array $nodes, bool $menu = false): void
    {
        /** @var array<string, true> $visited */
        $visited = [];
        $visit = function (string $node, array $path) use (&$visit, &$visited, $graph, $nodes, $menu): void {
            if (in_array($node, $path, true)) {
                $cycle = implode(' -> ', [...$path, $node]);

                if ($menu) {
                    throw CompilationException::menuCycle($cycle);
                }

                $point = $nodes[$node]->point ?? 'unknown';

                throw CompilationException::replacementCycle($point, $cycle);
            }

            if (isset($visited[$node])) {
                return;
            }

            foreach ($graph[$node] ?? [] as $next) {
                $visit($next, [...$path, $node]);
            }

            $visited[$node] = true;
        };

        foreach (array_keys($graph) as $node) {
            $visit($node, []);
        }
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
