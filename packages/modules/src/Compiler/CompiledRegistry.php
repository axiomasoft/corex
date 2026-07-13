<?php

declare(strict_types=1);

namespace Flex\Modules\Compiler;

use Flex\Modules\Extend\Api;
use Flex\Modules\Extend\Filament;
use Flex\Modules\Extend\Menu;
use Flex\Modules\Extend\Permissions;
use Flex\Modules\Extend\Routes;
use Flex\Modules\Extend\Workflow;
use Flex\Modules\Registry\EntityDefinition;

/**
 * In-memory result of RegistryCompiler::compile() (B-10 §5.2). Atomic write
 * to `bootstrap/cache/flex_modules.php` and hot-path reads are P1.5 — this
 * DTO is the compiler's sole output.
 */
final readonly class CompiledRegistry
{
    /**
     * @param  array<string, EntityDefinition>  $entities  keyed by handle
     * @param  list<CompiledEntityField>  $entityFields
     * @param  list<Permissions>  $permissions
     * @param  list<Api>  $api
     * @param  list<Filament>  $filament
     * @param  list<Menu>  $menu
     * @param  list<Routes>  $routes
     * @param  list<Workflow>  $workflow
     */
    public function __construct(
        public array $entities,
        public array $entityFields,
        public array $permissions,
        public array $api,
        public array $filament,
        public array $menu,
        public array $routes,
        public array $workflow,
    ) {}
}
