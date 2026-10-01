<?php

declare(strict_types=1);

namespace CoreX\Messaging\PHPStan\Rules;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Messaging\Internal\DurablePayloadTypeChecker;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;

/**
 * Promoted constructor properties are not {@see Property} AST nodes.
 *
 * @implements Rule<ClassMethod>
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
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
            if (! $param->isPromoted() || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }
            $propertyName = $param->var->name;
            $propertyReflection = $classReflection->getNativeProperty($propertyName);
            $readableType = $propertyReflection->getReadableType();
            $forbidden = DurablePayloadTypeChecker::forbiddenInType($readableType);

            if ($forbidden !== null) {
                $errors[] = DurablePayloadTypeChecker::buildError($classReflection->getDisplayName(), $propertyName, $forbidden, $param->getStartLine());
            }
            $errors = [...$errors, ...DurablePayloadTypeChecker::untypedArrayErrors(type: $readableType, propertyName: $propertyName, line: $param->getStartLine())];
        }

        return $errors;
    }
}
