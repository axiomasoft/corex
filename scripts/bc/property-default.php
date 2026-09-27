<?php

declare(strict_types=1);

namespace Roave\BackwardCompatibility\DetectChanges\BCBreak\PropertyBased;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\PrettyPrinter\Standard;
use Roave\BackwardCompatibility\Change;
use Roave\BackwardCompatibility\Changes;
use Roave\BackwardCompatibility\Formatter\ReflectionPropertyName;
use Roave\BetterReflection\NodeCompiler\Exception\UnableToCompileNode;
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

            // Only enum-case compiler limitations qualify. Missing dependencies,
            // arbitrary expressions and unresolved constants still fail closed.
            if (! str_contains($error->getMessage(), 'An enum expression ')
                || ! $fromExpression instanceof ClassConstFetch
                || ! $toExpression instanceof ClassConstFetch) {
                throw $error;
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
