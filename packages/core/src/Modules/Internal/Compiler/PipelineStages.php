<?php

declare(strict_types=1);

namespace CoreX\Modules\Internal\Compiler;

use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\Entity;
use CoreX\Modules\Data\EntityField;
use CoreX\Modules\Data\Permissions;
use CoreX\Modules\Internal\Extend\Filament;
use CoreX\Modules\Internal\Extend\Menu;
use CoreX\Modules\Internal\Extend\Routes;
use CoreX\Modules\Internal\Extend\Workflow;

/**
 * The single place the closed 8-extender pipeline order is declared —
 * previously duplicated implicitly across {@see RegistryCompiler}
 * method-call order and {@see CompilationValidator}'s membership check,
 * with nothing enforcing they agreed. {@see RegistryCompiler::compile()}
 * drives its stage loop off this constant and {@see CompilationValidator}
 * reads the same array for closed-set membership — reordering the pipeline
 * means editing this one array, and doing so is directly observable by a
 * compile-time test (RegistryCompilerTest "pipeline stage order").
 *
 * @internal spec: B-10 §4.2/§5.2, AC-18/AC-19, A85
 */
final class PipelineStages
{
    /** @var list<class-string> */
    public const array ORDER = [
        Entity::class,
        EntityField::class,
        Permissions::class,
        Api::class,
        Filament::class,
        Menu::class,
        Routes::class,
        Workflow::class,
    ];
}
