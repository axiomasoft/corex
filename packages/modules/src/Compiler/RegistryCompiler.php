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
     * @param  list<string>  $installedPackages  composer names installed but without their own
     *                                           corex-module manifest in this pass (e.g. corex/core).
     */
    public function compile(array $manifests, array $installedPackages = ['corex/core']): CompiledRegistry
    {
        $this->validator->validateExtenderSet($manifests);
        $this->validator->validateTablePrefixes($manifests);
        $this->validator->validateRequires($manifests, $installedPackages);
        $this->validator->validateComponents($manifests);

        $entities = $this->applyEntityExtenders($manifests);
        $entityFields = $this->applyEntityFieldExtenders($manifests, $entities);

        return new CompiledRegistry(
            entities: $entities,
            entityFields: $entityFields,
            permissions: $this->collect($manifests, Permissions::class),
            api: $this->collect($manifests, Api::class),
            filament: $this->collect($manifests, Filament::class),
            menu: $this->collect($manifests, Menu::class),
            routes: $this->collect($manifests, Routes::class),
            workflow: $this->collect($manifests, Workflow::class),
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
                $entities[$definition->handle] = $definition;
            }
        }

        return $entities;
    }

    private function compileEntity(Manifest $manifest, Entity $extender): EntityDefinition
    {
        $modulePrefix = $manifest->tablePrefix ?? Str::of($manifest->composerName)->after('/')->limit(3, '')->lower()->toString();
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
}
