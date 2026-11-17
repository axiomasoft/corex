<?php

declare(strict_types=1);

namespace CoreX\Access\Extend\Internal;

use CoreX\Access\Contracts\RoleMatrixCompiler;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Events\Lifecycle\ModuleDisabled;
use Packman\Modules\Events\Lifecycle\ModuleEnabled;

/**
 * Ties `aut_role_matrix` compilation to module lifecycle (AC-27, D112/D113):
 * on ANY enable/disable, {@see RoleMatrixCompiler::recompileAll()} re-runs,
 * so active-entity checks are re-evaluated against
 * the module's new state. This class creates NO `aut_role_matrix` rows
 * itself (D113 — the merger only merges the SURFACE, modules write the
 * matrix); the rows a disabled module's entities leave behind stay in the
 * table for re-enable.
 *
 * `merge()` also validates `CompiledRegistry::$permissions` declarations
 * against {@see EntityRegistry}: a declaration whose `entity` is unknown to
 * the registry cannot be attributed to any module (the compiled slot
 * carries no module attribution of its own, findings/P2.9-seam-inventory
 * §6) and is skipped fail-closed — surfaced as a warning in the return
 * value, never a log (same contract as {@see CoreX\Access\Matrix\Internal\MatrixValidator}).
 *
 * @internal spec: B-11 §4 п.2/п.4, P2.21 D112/D113, AC-27
 */
final readonly class PermissionsMerger
{
    public function __construct(
        private ModuleRegistry $modules,
        private EntityRegistry $entities,
        private RoleMatrixCompiler $compiler,
    ) {}

    public function __invoke(ModuleEnabled|ModuleDisabled $event): void
    {
        $this->merge();
    }

    /** @return list<string> warnings for Extend\Permissions declarations whose entity is unknown to EntityRegistry */
    public function merge(): array
    {
        $warnings = [];

        foreach ($this->modules->compiled()->permissions as $declaration) {
            if (! $this->entities->has($declaration->entity)) {
                $warnings[] = sprintf(
                    "Extend\\Permissions declares unknown entity '%s' — declaration ignored, no matrix row can attribute to a module.",
                    $declaration->entity,
                );
            }
        }

        $this->compiler->recompileAll();

        return $warnings;
    }
}
