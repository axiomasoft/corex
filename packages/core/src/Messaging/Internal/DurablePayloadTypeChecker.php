<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Messaging\Contracts\DurablePayload;
use CoreX\Messaging\PHPStan\Rules\DurablePayloadRule;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;

final class DurablePayloadTypeChecker
{
    /**
     * @var list<class-string>
     */
    private const FORBIDDEN = ['Illuminate\Database\Eloquent\Model', 'Closure', 'Illuminate\Container\Container', 'Illuminate\Contracts\Container\Container', 'Psr\Container\ContainerInterface', 'Illuminate\Http\Request', 'Symfony\Component\HttpFoundation\Request', 'CoreX\Tenancy\Contracts\TenantContext'];

    public static function implementsMarker(ClassReflection $classReflection): bool
    {
        return $classReflection->implementsInterface(DurablePayload::class);
    }

    public static function forbiddenInType(Type $type): ?string
    {
        $matched = null;
        TypeTraverser::map($type, function (Type $inner, callable $traverse) use (&$matched) {
            if ($matched !== null) {
                return $inner;
            }

            if ($inner instanceof ObjectType) {
                $matched = self::matchClassName($inner->getClassName());
            }

            return $traverse($inner);
        });

        return $matched;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public static function untypedArrayErrors(Type $type, string $propertyName, int $line): array
    {
        $errors = [];
        foreach ($type->getArrays() as $arrayType) {
            $valueType = $arrayType->getIterableValueType();

            if (! $valueType instanceof MixedType) {
                continue;
            }
            $errors[] = RuleErrorBuilder::message(sprintf('Durable payload property $%s declares an array with %s; specify the element type.', $propertyName, $valueType->isExplicitMixed() ? 'an explicit mixed value type' : 'a missing value type'))->identifier(DurablePayloadRule::UNTYPED_ARRAY_IDENTIFIER)->line($line)->build();
        }

        return $errors;
    }

    public static function buildError(string $className, string $propertyName, string $forbidden, int $line): IdentifierRuleError
    {
        return RuleErrorBuilder::message(sprintf('Durable payload %s::$%s must not contain %s', $className, $propertyName, $forbidden))->identifier(DurablePayloadRule::IDENTIFIER)->line($line)->build();
    }

    private static function matchClassName(string $className): ?string
    {
        foreach (self::FORBIDDEN as $forbidden) {
            if (is_a($className, $forbidden, true)) {
                return $forbidden;
            }
        }

        return null;
    }
}
