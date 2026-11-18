<?php

declare(strict_types=1);

namespace CoreX\Messaging\PHPStan\Rules;

use CoreX\Messaging\Contracts\DurablePayload;
use CoreX\Messaging\PHPStan\DurablePayloadTypeChecker;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use PhpParser\Node;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * RUN-002/003: у типов с маркером {@see DurablePayload} запрещены несериализуемые
 * объявленные свойства.
 *
 * @implements Rule<Property>
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class DurablePayloadRule implements Rule
{
    public const string IDENTIFIER = 'corex.durablePayload.nonSerializableProperty';

    public const string UNTYPED_ARRAY_IDENTIFIER = 'corex.durablePayload.untypedArrayProperty';

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
                $errors[] = DurablePayloadTypeChecker::buildError(className: $classReflection->getDisplayName(), propertyName: $propertyName, forbidden: $forbidden, line: $property->getStartLine());
            }
            $errors = [...$errors, ...DurablePayloadTypeChecker::untypedArrayErrors(type: $readableType, propertyName: $propertyName, line: $property->getStartLine())];
        }

        return $errors;
    }
}
