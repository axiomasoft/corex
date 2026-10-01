<?php

declare(strict_types=1);

namespace Roave\BackwardCompatibility\DetectChanges\BCBreak\PropertyBased;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\PrettyPrinter\Standard;
use Roave\BackwardCompatibility\Change;
use Roave\BackwardCompatibility\Changes;
use Roave\BackwardCompatibility\Formatter\ReflectionPropertyName;
use Roave\BetterReflection\NodeCompiler\Exception\UnableToCompileNode;
use Roave\BetterReflection\Reflection\ReflectionNamedType;
use Roave\BetterReflection\Reflection\ReflectionProperty;

/** Roave 8.22 property counterpart of its parameter-default expression fallback. */
final class PropertyDefaultValueChanged implements PropertyBased
{
    public function __invoke(ReflectionProperty $fromProperty, ReflectionProperty $toProperty): Changes
    {
        try {
            $from = $fromProperty->getDefaultValue();
            $to = $toProperty->getDefaultValue();
        } catch (UnableToCompileNode $error) {
            $fromExpression = $fromProperty->getDefaultValueExpression();
            $toExpression = $toProperty->getDefaultValueExpression();

            $enum = str_contains($error->getMessage(), 'An enum expression ')
                && $fromExpression instanceof ClassConstFetch && $toExpression instanceof ClassConstFetch;
            $initializer = str_contains($error->getMessage(), 'Unable to compile initializer ')
                && $fromExpression instanceof New_ && $toExpression instanceof New_;

            if (! $enum && ! $initializer) {
                throw $error;
            }

            if ($initializer) {
                // Resolve the declared dependency before the AST fallback. Missing
                // classes and unsupported/untyped initializers remain infrastructure RED.
                foreach ([[$fromProperty, $fromExpression], [$toProperty, $toExpression]] as [$property, $expression]) {
                    $type = $property->getType();

                    if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                        throw $error;
                    }

                    if (! $expression->class instanceof Name
                        || ltrim($expression->class->toString(), '\\') !== $type->getClass()->getName()) {
                        throw $error;
                    }
                }
            }
            $printer = new Standard;
            $from = $printer->prettyPrintExpr($fromExpression);
            $to = $printer->prettyPrintExpr($toExpression);
        }

        if ($from === $to) {
            return Changes::empty();
        }

        return Changes::fromList(Change::changed(sprintf(
            'Property %s changed default value from %s to %s',
            (new ReflectionPropertyName)($fromProperty),
            var_export($from, true),
            var_export($to, true),
        )));
    }
}
