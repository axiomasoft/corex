<?php

declare(strict_types=1);

use CoreX\Contracts\FeatureFlagDefinition;
use CoreX\Contracts\SettingsScope;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\Extend\Entity;
use CoreX\Modules\Extend\EntityField;
use CoreX\Modules\Extend\Filament;
use CoreX\Modules\Extend\Menu;
use CoreX\Modules\Extend\Permissions;
use CoreX\Modules\Extend\Routes;
use CoreX\Modules\Extend\Workflow;
use CoreX\Modules\SettingDefault;

// Fluent-canon pin (P3.6, A13): a manifest-authoring DTO in Contracts/Extend
// is never built with `new` by a module author — it exposes at least one
// public static factory constructor instead. Add new manifest DTOs here
// when they are introduced; a class landing on this list without a factory
// is exactly the drift this test exists to catch.
$manifestDtoClasses = [
    FeatureFlagDefinition::class,
    SettingDefault::class,
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

            if ($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), ['self', 'static'], true)) {
                $hasFactory = true;

                break;
            }
        }

        expect($hasFactory)->toBeTrue("{$class} has no public static factory constructor returning self/static.");
    }
});
