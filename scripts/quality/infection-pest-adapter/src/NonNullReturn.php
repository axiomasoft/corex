<?php

declare(strict_types=1);

namespace CoreX\Tooling\Infection;

use Infection\Mutator\Definition;
use Infection\Mutator\Mutator;
use Infection\Mutator\MutatorCategory;
use PhpParser\Node;

/** @implements Mutator<Node\Stmt\Return_> */
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
        if (! $node instanceof Node\Stmt\Return_
            || ! ($node->expr instanceof Node\Expr\MethodCall || $node->expr instanceof Node\Expr\StaticCall
                || $node->expr instanceof Node\Expr\FuncCall || $node->expr instanceof Node\Expr\PropertyFetch
                || $node->expr instanceof Node\Expr\ArrayDimFetch || $node->expr instanceof Node\Expr\Variable
                || $node->expr instanceof Node\Expr\New_)) {
            return false;
        }
        $scope = $node->getAttribute('parent');
        while ($scope instanceof Node && ! ($scope instanceof Node\Stmt\ClassMethod
            || $scope instanceof Node\Stmt\Function_ || $scope instanceof Node\Expr\Closure)) {
            $scope = $scope->getAttribute('parent');
        }

        if (! ($scope instanceof Node\Stmt\ClassMethod || $scope instanceof Node\Stmt\Function_
            || $scope instanceof Node\Expr\Closure)) {
            return false;
        }
        $type = $scope->returnType;

        return $type instanceof Node\Name || ($type instanceof Node\Identifier
            && ! in_array($type->toLowerString(), ['void', 'never', 'mixed', 'null'], true));
    }

    /** @return iterable<Node\Stmt\Return_> */
    public function mutate(Node $node): iterable
    {
        yield new Node\Stmt\Return_(new Node\Expr\ConstFetch(new Node\Name('null')));
    }
}
