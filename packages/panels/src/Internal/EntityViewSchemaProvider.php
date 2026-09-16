<?php

declare(strict_types=1);

namespace CoreX\Panels\Internal;

use CoreX\Contracts\ViewSchemaProvider;
use CoreX\Modules\Contracts\EntityRegistry;
use CoreX\Modules\Registry\FieldDefinition;
use CoreX\Views\Data\ViewContext;
use CoreX\Views\Data\ViewField;
use CoreX\Views\Data\ViewSchema;
use LogicException;

/** @internal */
final class EntityViewSchemaProvider implements ViewSchemaProvider
{
    public function __construct(private readonly EntityRegistry $entities) {}

    public function schema(ViewContext $context, string $entityHandle): ViewSchema
    {
        if (! $this->entities->has($entityHandle) || ! in_array($this->entities->get($entityHandle), $this->entities->all($context->tenant), true)) {
            throw new LogicException('Saved-view entity schema is unavailable.');
        }
        $entity = $this->entities->get($entityHandle);

        return new ViewSchema('modules/1', $entityHandle, array_map(
            static fn (FieldDefinition $field): ViewField => new ViewField(
                key: $field->handle,
                type: $field->type,
                operators: self::operators($field->type),
                visible: true,
                filterable: true,
                sortable: true,
                projectable: true,
                mappingKey: $field->handle,
            ),
            $entity->fields,
        ));
    }

    /** @return list<string> */
    private static function operators(string $type): array
    {
        return match ($type) {
            'bool' => ['eq', 'ne', 'is_null'],
            'int', 'decimal', 'money', 'date', 'datetime' => ['eq', 'ne', 'lt', 'lte', 'gt', 'gte', 'in', 'is_null'],
            default => ['eq', 'ne', 'in', 'is_null'],
        };
    }
}
