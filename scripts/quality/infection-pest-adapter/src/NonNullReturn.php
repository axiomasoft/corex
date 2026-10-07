<?php

declare(strict_types=1);

namespace CoreX\Tooling\Infection;

use Infection\Mutator\Definition;
use Infection\Mutator\Mutator;
use Infection\Mutator\MutatorCategory;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;

/** @implements Mutator<Return_> */
final class NonNullReturn implements Mutator
{
    public function getName(): string
    {
        return 'NonNullReturn';
    }

    public static function getDefinition(): Definition
    {
        return new Definition(
            'Replace a strictly typed forwarded return value with null to verify its observable contract.',
            MutatorCategory::ORTHOGONAL_REPLACEMENT,
            null,
            '- return Config::value();'."\n".'+ return null;',
        );
    }

    public function canMutate(Node $node): bool
    {
        if (! $node instanceof Return_
            || ! ($node->expr instanceof MethodCall || $node->expr instanceof StaticCall
                || $node->expr instanceof FuncCall || $node->expr instanceof PropertyFetch
                || $node->expr instanceof ArrayDimFetch || $node->expr instanceof Variable
                || $node->expr instanceof New_)) {
            return false;
        }
        $scope = $node->getAttribute('parent');
        while ($scope instanceof Node && ! ($scope instanceof ClassMethod
            || $scope instanceof Function_ || $scope instanceof Closure)) {
            $scope = $scope->getAttribute('parent');
        }

        if (! ($scope instanceof ClassMethod || $scope instanceof Function_
            || $scope instanceof Closure)) {
            return false;
        }
        $type = $scope->returnType;

        return $type instanceof Name || ($type instanceof Identifier
            && ! in_array($type->toLowerString(), ['void', 'never', 'mixed', 'null'], true));
    }

    /** @return iterable<Return_> */
    public function mutate(Node $node): iterable
    {
        yield new Return_(new ConstFetch(new Name('null')));
    }
}
