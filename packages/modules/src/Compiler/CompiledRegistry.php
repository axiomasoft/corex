<?php

declare(strict_types=1);

namespace Flex\Modules\Compiler;

use Flex\Modules\Extend\Api;
use Flex\Modules\Extend\Extender;
use Flex\Modules\Extend\Filament;
use Flex\Modules\Extend\Menu;
use Flex\Modules\Extend\Permissions;
use Flex\Modules\Extend\Routes;
use Flex\Modules\Extend\Workflow;
use Flex\Modules\Registry\EntityDefinition;
use ReflectionClass;

/**
 * In-memory result of RegistryCompiler::compile() (B-10 §5.2). The compiled
 * file `bootstrap/cache/flex_modules.php` persists this whole DTO (all 8
 * slices) via {@see toArray()}/{@see fromArray()} (P1.15) so the runtime can
 * project entities into EntityRegistry and the morph map without re-running
 * the compiler on every request.
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

    public static function empty(): self
    {
        return new self(
            entities: [],
            entityFields: [],
            permissions: [],
            api: [],
            filament: [],
            menu: [],
            routes: [],
            workflow: [],
        );
    }

    /**
     * Plain-array projection of all 8 slices for the compiled file. Entity
     * definitions keep their handle keys; the six extender slices flatten to
     * their public scalar properties via {@see extenderToArray()} — the
     * concrete extender class per slice is fixed, so {@see fromArray()} knows
     * exactly which class to rehydrate without a discriminator.
     *
     * @return array{entities: array<string, array<string, mixed>>, entityFields: list<array<string, mixed>>, permissions: list<array<string, mixed>>, api: list<array<string, mixed>>, filament: list<array<string, mixed>>, menu: list<array<string, mixed>>, routes: list<array<string, mixed>>, workflow: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'entities' => array_map(static fn (EntityDefinition $entity): array => $entity->toArray(), $this->entities),
            'entityFields' => array_map(static fn (CompiledEntityField $field): array => $field->toArray(), $this->entityFields),
            'permissions' => array_map(self::extenderToArray(...), $this->permissions),
            'api' => array_map(self::extenderToArray(...), $this->api),
            'filament' => array_map(self::extenderToArray(...), $this->filament),
            'menu' => array_map(self::extenderToArray(...), $this->menu),
            'routes' => array_map(self::extenderToArray(...), $this->routes),
            'workflow' => array_map(self::extenderToArray(...), $this->workflow),
        ];
    }

    /**
     * @param  array{entities?: array<string, array<string, mixed>>, entityFields?: list<array<string, mixed>>, permissions?: list<array<string, mixed>>, api?: list<array<string, mixed>>, filament?: list<array<string, mixed>>, menu?: list<array<string, mixed>>, routes?: list<array<string, mixed>>, workflow?: list<array<string, mixed>>}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            entities: array_map(
                static fn (array $entity): EntityDefinition => EntityDefinition::fromArray($entity),
                $data['entities'] ?? [],
            ),
            entityFields: array_map(
                static fn (array $field): CompiledEntityField => CompiledEntityField::fromArray($field),
                $data['entityFields'] ?? [],
            ),
            permissions: array_map(static fn (array $row): Permissions => self::extenderFromArray(Permissions::class, $row), $data['permissions'] ?? []),
            api: array_map(static fn (array $row): Api => self::extenderFromArray(Api::class, $row), $data['api'] ?? []),
            filament: array_map(static fn (array $row): Filament => self::extenderFromArray(Filament::class, $row), $data['filament'] ?? []),
            menu: array_map(static fn (array $row): Menu => self::extenderFromArray(Menu::class, $row), $data['menu'] ?? []),
            routes: array_map(static fn (array $row): Routes => self::extenderFromArray(Routes::class, $row), $data['routes'] ?? []),
            workflow: array_map(static fn (array $row): Workflow => self::extenderFromArray(Workflow::class, $row), $data['workflow'] ?? []),
        );
    }

    /**
     * Flatten an extender to its public scalar/array properties. Every
     * extender in the closed set (B-10 §4.2) holds only scalars and
     * arrays-of-scalars (class-strings stay strings) — none nest objects —
     * so `get_object_vars()` yields a var_export'able array with no
     * `__set_state` and no closures. Shared by {@see CompiledEntityField}.
     *
     * @return array<string, mixed>
     */
    public static function extenderToArray(Extender $extender): array
    {
        return get_object_vars($extender);
    }

    /**
     * Rebuild an extender from its flattened array. Reconstruction is by
     * property assignment (not the private factory methods, which the runtime
     * has no data to feed) — the inverse of {@see extenderToArray()}, and the
     * only reason `__set_state` is not needed in the persisted file.
     *
     * @template T of Extender
     *
     * @param  class-string<T>  $class
     * @param  array<string, mixed>  $data
     * @return T
     */
    public static function extenderFromArray(string $class, array $data): Extender
    {
        $reflection = new ReflectionClass($class);
        $extender = $reflection->newInstanceWithoutConstructor();

        foreach ($data as $property => $value) {
            if ($reflection->hasProperty($property)) {
                $reflection->getProperty($property)->setValue($extender, $value);
            }
        }

        return $extender;
    }
}
