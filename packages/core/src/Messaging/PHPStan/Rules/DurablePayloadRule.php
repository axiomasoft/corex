<?php

declare(strict_types=1);

namespace CoreX\Messaging\PHPStan\Rules;

use CoreX\Messaging\Contracts\DurablePayload;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;

/**
 * RUN-002/003: у типов с маркером {@see DurablePayload} запрещены несериализуемые
 * объявленные свойства.
 *
 * @implements Rule<Property>
 *
 * @internal spec: P3-design.md §7, P4-design.md §5.2
 */
final class DurablePayloadRule implements Rule
{
    public const IDENTIFIER = 'corex.durablePayload.nonSerializableProperty';

    public const UNTYPED_ARRAY_IDENTIFIER = 'corex.durablePayload.untypedArrayProperty';

    public function getNodeType(): string
    {
        return Property::class;
    }

    /**
     * @param  Property  $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null || ! DurablePayloadTypeChecker::implementsMarker($classReflection)) {
            return [];
        }

        $errors = [];

        foreach ($node->props as $property) {
            $propertyName = $property->name->toString();
            $propertyReflection = $classReflection->getNativeProperty($propertyName);
            $readableType = $propertyReflection->getReadableType();

            $forbidden = DurablePayloadTypeChecker::forbiddenInType($readableType);

            if ($forbidden !== null) {
                $errors[] = DurablePayloadTypeChecker::buildError(
                    $classReflection->getDisplayName(),
                    $propertyName,
                    $forbidden,
                    $property->getStartLine(),
                );
            }

            $errors = [
                ...$errors,
                ...DurablePayloadTypeChecker::untypedArrayErrors(
                    type: $readableType,
                    propertyName: $propertyName,
                    line: $property->getStartLine(),
                ),
            ];
        }

        return $errors;
    }
}

/**
 * Promoted constructor properties are not {@see Property} AST nodes.
 *
 * @implements Rule<ClassMethod>
 */
final class DurablePayloadPromotedPropertyRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    /**
     * @param  ClassMethod  $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->name->toString() !== '__construct') {
            return [];
        }

        $classReflection = $scope->getClassReflection();

        if ($classReflection === null || ! DurablePayloadTypeChecker::implementsMarker($classReflection)) {
            return [];
        }

        $errors = [];

        foreach ($node->params as $param) {
            if (! $param->isPromoted()) {
                continue;
            }

            $propertyName = $param->var->name;
            $propertyReflection = $classReflection->getNativeProperty($propertyName);
            $readableType = $propertyReflection->getReadableType();

            $forbidden = DurablePayloadTypeChecker::forbiddenInType($readableType);

            if ($forbidden !== null) {
                $errors[] = DurablePayloadTypeChecker::buildError(
                    $classReflection->getDisplayName(),
                    $propertyName,
                    $forbidden,
                    $param->getStartLine(),
                );
            }

            $errors = [
                ...$errors,
                ...DurablePayloadTypeChecker::untypedArrayErrors(
                    type: $readableType,
                    propertyName: $propertyName,
                    line: $param->getStartLine(),
                ),
            ];
        }

        return $errors;
    }
}

/**
 * @internal
 */
final class DurablePayloadTypeChecker
{
    /**
     * @var list<class-string>
     */
    private const FORBIDDEN = [
        'Illuminate\Database\Eloquent\Model',
        'Closure',
        'Illuminate\Container\Container',
        'Illuminate\Contracts\Container\Container',
        'Psr\Container\ContainerInterface',
        'Illuminate\Http\Request',
        'Symfony\Component\HttpFoundation\Request',
        'CoreX\Tenancy\Contracts\TenantContext',
    ];

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

            $errors[] = RuleErrorBuilder::message(sprintf(
                'Durable payload property $%s declares an array with %s; specify the element type.',
                $propertyName,
                $valueType->isExplicitMixed() ? 'an explicit mixed value type' : 'a missing value type',
            ))
                ->identifier(DurablePayloadRule::UNTYPED_ARRAY_IDENTIFIER)
                ->line($line)
                ->build();
        }

        return $errors;
    }

    public static function buildError(
        string $className,
        string $propertyName,
        string $forbidden,
        int $line,
    ): IdentifierRuleError {
        return RuleErrorBuilder::message(sprintf(
            'Durable payload %s::$%s must not contain %s',
            $className,
            $propertyName,
            $forbidden,
        ))
            ->identifier(DurablePayloadRule::IDENTIFIER)
            ->line($line)
            ->build();
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
