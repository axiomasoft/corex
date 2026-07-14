<?php

declare(strict_types=1);

namespace Flex\Modules\Compiler;

use CoreX\Money\MoneyCast;
use Flex\Modules\Enums\Capability;
use Flex\Modules\Exceptions\CompilationException;
use Flex\Modules\Extend\Api;
use Flex\Modules\Extend\Entity;
use Flex\Modules\Extend\EntityField;
use Flex\Modules\Extend\Extender;
use Flex\Modules\Extend\Filament;
use Flex\Modules\Extend\Menu;
use Flex\Modules\Extend\Permissions;
use Flex\Modules\Extend\Routes;
use Flex\Modules\Extend\Workflow;
use Flex\Modules\Manifest;
use Flex\Modules\Registry\EntityDefinition;
use Flex\Modules\Registry\FieldDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Applies the closed 8-extender set to a set of manifests in a fixed,
 * deterministic pipeline (B-10 §4.2/§5.2): Entity → EntityField →
 * Permissions → Api → Filament → Menu → Routes → Workflow. Output is an
 * in-memory CompiledRegistry — atomic file write and hot-path reads belong
 * to ModuleRegistry (P1.5), not here.
 */
final class RegistryCompiler
{
    public function __construct(
        private readonly CompilationValidator $validator = new CompilationValidator,
    ) {}

    /**
     * @param  list<Manifest>  $manifests
     * @param  list<string>|null  $installedPackages  composer names known installed beyond
     *                                                $manifests (AC-2). Defaults to a real read of
     *                                                `vendor/composer/installed.json` ({@see
     *                                                InstalledPackages}) — pass an explicit list in
     *                                                tests/testbench to avoid depending on the host's
     *                                                installed.json contents.
     * @param  (callable(class-string): void)|null  $onStage  observes each {@see PipelineStages}
     *                                                        entry as its stage starts, in the order
     *                                                        actually executed — test seam for AC-18
     *                                                        (pipeline determinism).
     */
    public function compile(array $manifests, ?array $installedPackages = null, ?callable $onStage = null): CompiledRegistry
    {
        $this->validator->validateRequiredFields($manifests);
        $this->validator->validateExtenderSet($manifests);
        $this->validator->validateTablePrefixes($manifests);
        $this->validator->validateRequires($manifests, $installedPackages ?? InstalledPackages::discover());
        $this->validator->validateComponents($manifests);

        $entities = [];
        $entityFields = [];
        $permissions = [];
        $api = [];
        $filament = [];
        $menu = [];
        $routes = [];
        $workflow = [];

        foreach (PipelineStages::ORDER as $stage) {
            if ($onStage !== null) {
                $onStage($stage);
            }

            match ($stage) {
                Entity::class => $entities = $this->applyEntityExtenders($manifests),
                EntityField::class => $entityFields = $this->applyEntityFieldExtenders($manifests, $entities),
                Permissions::class => $permissions = $this->collectPermissions($manifests),
                Api::class => $api = $this->collectApi($manifests),
                Filament::class => $filament = $this->collectFilament($manifests),
                Menu::class => $menu = $this->collectMenu($manifests),
                Routes::class => $routes = $this->collectRoutes($manifests),
                Workflow::class => $workflow = $this->collectWorkflow($manifests),
            };
        }

        return new CompiledRegistry(
            entities: $entities,
            entityFields: $entityFields,
            permissions: $permissions,
            api: $api,
            filament: $filament,
            menu: $menu,
            routes: $routes,
            workflow: $workflow,
        );
    }

    /**
     * @param  list<Manifest>  $manifests
     * @return array<string, EntityDefinition>
     */
    private function applyEntityExtenders(array $manifests): array
    {
        $entities = [];

        foreach ($manifests as $manifest) {
            foreach ($manifest->extenders as $extender) {
                if (! $extender instanceof Entity) {
                    continue;
                }

                $definition = $this->compileEntity($manifest, $extender);

                if (isset($entities[$definition->handle])) {
                    throw CompilationException::duplicateEntityHandle(
                        $definition->handle,
                        $entities[$definition->handle]->module,
                        $manifest->composerName,
                    );
                }

                $entities[$definition->handle] = $definition;
            }
        }

        return $entities;
    }

    private function compileEntity(Manifest $manifest, Entity $extender): EntityDefinition
    {
        $modulePrefix = $manifest->tablePrefix;

        if ($modulePrefix === null) {
            // Guarded by CompilationValidator::validateRequiredFields() before the pipeline
            // starts (D37: tablePrefix is mandatory, no auto-derivation from the composer
            // name); this branch only matters if compileEntity() is ever reached directly.
            throw CompilationException::missingTablePrefix($manifest->composerName);
        }

        $handle = $extender->handle ?? sprintf('%s.%s', $modulePrefix, Str::snake(class_basename($extender->model)));
        $table = $extender->table ?? $modulePrefix.'_'.Str::snake(Str::plural(class_basename($extender->model)));

        if (! str_starts_with($table, $modulePrefix.'_')) {
            throw CompilationException::tablePrefixMismatch($manifest->composerName, $table, $modulePrefix);
        }

        $labelSingular = $extender->labelSingular ?? Str::headline(class_basename($extender->model));

        return new EntityDefinition(
            handle: $handle,
            model: $extender->model,
            table: $table,
            module: $manifest->composerName,
            labelSingular: $labelSingular,
            labelPlural: $extender->labelPlural ?? Str::plural($labelSingular),
            capabilities: $this->capabilitiesFor($extender),
            fields: $extender->fieldDefinitions ?? $this->introspectCasts($extender->model),
            titleAttribute: $extender->titleAttribute,
        );
    }

    /** @return list<Capability> */
    private function capabilitiesFor(Entity $extender): array
    {
        $capabilities = [];

        if ($extender->isSearchable) {
            $capabilities[] = Capability::Searchable;
        }

        if ($extender->isAuditable) {
            $capabilities[] = Capability::Auditable;
        }

        if ($extender->hasCustomFields) {
            $capabilities[] = Capability::CustomFields;
        }

        if ($extender->isWorkflowable) {
            $capabilities[] = Capability::Workflowable;
        }

        if ($extender->isCommentable) {
            $capabilities[] = Capability::Commentable;
        }

        if ($extender->isDocumentable) {
            $capabilities[] = Capability::Documentable;
        }

        return $capabilities;
    }

    /**
     * Falls back to introspecting the model's `$casts` when the extender
     * doesn't declare fields explicitly (B-10 §4.2).
     *
     * @param  class-string<Model>  $model
     * @return list<FieldDefinition>
     */
    private function introspectCasts(string $model): array
    {
        $instance = new $model;

        $fields = [];

        foreach ($instance->getCasts() as $attribute => $cast) {
            $fields[] = new FieldDefinition(handle: $attribute, type: $this->mapCastType($cast));
        }

        return $fields;
    }

    /** @return 'string'|'int'|'decimal'|'bool'|'date'|'datetime'|'json'|'money'|'relation'|'enum' */
    private function mapCastType(string $cast): string
    {
        return match (true) {
            $cast === MoneyCast::class || str_starts_with($cast, MoneyCast::class.':') => 'money',
            in_array($cast, ['int', 'integer'], true) => 'int',
            str_starts_with($cast, 'decimal:') => 'decimal',
            in_array($cast, ['bool', 'boolean'], true) => 'bool',
            $cast === 'date' => 'date',
            str_starts_with($cast, 'datetime') || str_starts_with($cast, 'immutable_datetime') => 'datetime',
            in_array($cast, ['array', 'json', 'collection', 'object'], true) => 'json',
            default => 'string',
        };
    }

    /**
     * @param  list<Manifest>  $manifests
     * @param  array<string, EntityDefinition>  $entities
     * @return list<CompiledEntityField>
     */
    private function applyEntityFieldExtenders(array $manifests, array $entities): array
    {
        $compiled = [];

        foreach ($manifests as $manifest) {
            foreach ($manifest->extenders as $extender) {
                if (! $extender instanceof EntityField) {
                    continue;
                }

                $target = $entities[$extender->entityHandle] ?? null;
                $this->validator->validateEntityHandleKnown($manifest->composerName, $extender->entityHandle, $target !== null);

                /** @var EntityDefinition $target */
                $compiled[] = new CompiledEntityField(
                    extender: $extender,
                    declaringModule: $manifest->composerName,
                    sleeping: $target->module !== $manifest->composerName,
                );
            }
        }

        return $compiled;
    }

    /**
     * @template T of Extender
     *
     * @param  list<Manifest>  $manifests
     * @param  class-string<T>  $extenderClass
     * @return list<T>
     */
    private function collect(array $manifests, string $extenderClass): array
    {
        $result = [];

        foreach ($manifests as $manifest) {
            foreach ($manifest->extenders as $extender) {
                if ($extender instanceof $extenderClass) {
                    $result[] = $extender;
                }
            }
        }

        return $result;
    }

    /**
     * Concretely-typed wrappers around {@see collect()} (one per remaining
     * extender kind, PipelineStages::ORDER slots 3–8). Template inference on
     * a direct `collect($manifests, X::class)` call loses T when the call
     * site sits inside a `match` arm; a named method with its own concrete
     * `@return` gives the match arm a type PHPStan doesn't have to re-derive.
     *
     * @param  list<Manifest>  $manifests
     * @return list<Permissions>
     */
    private function collectPermissions(array $manifests): array
    {
        return $this->collect($manifests, Permissions::class);
    }

    /**
     * @param  list<Manifest>  $manifests
     * @return list<Api>
     */
    private function collectApi(array $manifests): array
    {
        return $this->collect($manifests, Api::class);
    }

    /**
     * @param  list<Manifest>  $manifests
     * @return list<Filament>
     */
    private function collectFilament(array $manifests): array
    {
        return $this->collect($manifests, Filament::class);
    }

    /**
     * @param  list<Manifest>  $manifests
     * @return list<Menu>
     */
    private function collectMenu(array $manifests): array
    {
        return $this->collect($manifests, Menu::class);
    }

    /**
     * @param  list<Manifest>  $manifests
     * @return list<Routes>
     */
    private function collectRoutes(array $manifests): array
    {
        return $this->collect($manifests, Routes::class);
    }

    /**
     * @param  list<Manifest>  $manifests
     * @return list<Workflow>
     */
    private function collectWorkflow(array $manifests): array
    {
        return $this->collect($manifests, Workflow::class);
    }
}
