<?php

declare(strict_types=1);

use CoreX\Contracts\FeatureFlagDefinition;
use CoreX\Contracts\SettingsScope;
use CoreX\Modules\DefaultSettingDefinition;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\Extend\Entity;
use CoreX\Modules\Extend\EntityField;
use CoreX\Modules\Extend\Filament;
use CoreX\Modules\Extend\Menu;
use CoreX\Modules\Extend\Permissions;
use CoreX\Modules\Extend\Routes;
use CoreX\Modules\Extend\Workflow;

// Fluent-canon pin (P3.6, A13): a manifest-authoring DTO in Contracts/Extend
// is never built with `new` by a module author — it exposes at least one
// public static factory constructor instead. Add new manifest DTOs here
// when they are introduced; a class landing on this list without a factory
// is exactly the drift this test exists to catch.
$manifestDtoClasses = [
    FeatureFlagDefinition::class,
    DefaultSettingDefinition::class,
    SettingsScope::class,
    Api::class,
    Entity::class,
    EntityField::class,
    Filament::class,
    Menu::class,
    Permissions::class,
    Routes::class,
    Workflow::class,
];

test('every manifest-authoring DTO exposes a public static factory constructor', function () use ($manifestDtoClasses): void {
    foreach ($manifestDtoClasses as $class) {
        $reflection = new ReflectionClass($class);

        $hasFactory = false;

        foreach ($reflection->getMethods() as $method) {
            if (! $method->isStatic() || ! $method->isPublic()) {
                continue;
            }

            $returnType = $method->getReturnType();

            if (! $returnType instanceof ReflectionNamedType) {
                continue;
            }

            $returnTypeName = $returnType->getName();

            // PHP 8.5: ReflectionNamedType::getName() resolves `self`/`static` to the
            // declaring class's FQCN instead of the literal keyword (8.4 and earlier
            // still report 'self'/'static' verbatim) — accept both forms.
            $isSelfOrStatic = in_array($returnTypeName, ['self', 'static'], true)
                || $returnTypeName === $class
                || is_a($returnTypeName, $class, true);

            if ($isSelfOrStatic) {
                $hasFactory = true;

                break;
            }
        }

        expect($hasFactory)->toBeTrue("{$class} has no public static factory constructor returning self/static.");
    }
});
