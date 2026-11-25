<?php

declare(strict_types=1);

namespace App\Lifecycle;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\CustomFields\Schemas\Contracts\DeclaredFields;
use CoreX\Data\Data\EntityHandle;
use Packman\Panels\SavedViews\Contracts\ViewAccessPolicy;
use Packman\Panels\SavedViews\Contracts\ViewSchemaProvider;
use Packman\Panels\SavedViews\Data\ViewAction;
use Packman\Panels\SavedViews\Data\ViewContext;
use Packman\Panels\SavedViews\Data\ViewField;
use Packman\Panels\SavedViews\Data\ViewSchema;
use Packman\Panels\SavedViews\Data\ViewScope;
use Packman\Panels\SavedViews\Data\ViewSubject;
use RuntimeException;

final class Views implements ViewAccessPolicy, ViewSchemaProvider
{
    public static function context(): ViewContext
    {
        $scope = Inventory::scope();

        return new ViewContext(resolve(TenantContextResolver::class)->current(), $scope->principalKind, $scope->principalId);
    }

    public static function scopes(): array
    {
        $scope = Inventory::scope();

        return [new ViewScope(Inventory::ENTITY, $scope->workspaceId, null, null),
            new ViewScope(Inventory::ENTITY, $scope->workspaceId, 'user', $scope->principalId)];
    }

    public function allows(ViewContext $context, ViewAction $action, ViewSubject $subject): bool
    {
        return $context == self::context() && in_array($subject->scope, self::scopes(), false);
    }

    public function schema(ViewContext $context, string $entityHandle): ViewSchema
    {
        if ($entityHandle !== Inventory::ENTITY || $context != self::context()) {
            throw new RuntimeException('Unrecognized view owner.');
        }
        foreach (app()->tagged(DeclaredFields::class) as $declaration) {
            if ($declaration->owner() !== Inventory::MODULE) {
                continue;
            }
            foreach ($declaration->fields(new EntityHandle($entityHandle)) as $field) {
                if ($field->machineKey === 'inspection_code' && $field->owner === Inventory::MODULE) {
                    return new ViewSchema(hash('sha256', json_encode($field->toArray(), JSON_THROW_ON_ERROR)), $entityHandle, [
                        new ViewField($field->machineKey, 'string', ['eq'], true, true, true, true, $field->id->value),
                    ]);
                }
            }
        }

        throw new RuntimeException('The installed field declaration is unavailable.');
    }
}
