<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\MethodCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use CoreX\Tooling\Rector\Laravel\NodeAnalyzer\QueryBuilderAnalyzer;
use Illuminate\Contracts\Database\Eloquent\Builder;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Type\ObjectType;

final class EloquentWhereTypeHintClosureParameterRector extends AbstractRector
{
    public function __construct(private readonly QueryBuilderAnalyzer $queryBuilderAnalyzer) {}

    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! ($node instanceof MethodCall || $node instanceof StaticCall)) {
            return null;
        }

        if ($this->isWhereMethodWithClosureOrArrowFunction($node)) {
            return $this->changeClosureParamType($node);
        }

        return null;
    }

    private function isWhereMethodWithClosureOrArrowFunction(MethodCall|StaticCall $node): bool
    {
        if (! $this->expectedObjectTypeAndMethodCall($node)) {
            return false;
        }

        return ($node->getArgs()[0]->value ?? null) instanceof Closure || ($node->getArgs()[0]->value ?? null) instanceof ArrowFunction;
    }

    private function changeClosureParamType(MethodCall|StaticCall $node): ?Node
    {
        /** @var ArrowFunction|Closure $closure */
        $closure = $node->getArgs()[0]->value;

        if (! isset($closure->getParams()[0])) {
            return null;
        }
        $param = $closure->getParams()[0];

        if ($param->type instanceof Name) {
            return null;
        }
        $classOrVar = $node instanceof MethodCall ? $node->var : $node->class;
        $type = $this->isObjectType($classOrVar, new ObjectType('Illuminate\Database\Eloquent\Model')) ? Builder::class : \Illuminate\Contracts\Database\Query\Builder::class;
        $param->type = new FullyQualified($type);

        return $node;
    }

    private function expectedObjectTypeAndMethodCall(MethodCall|StaticCall $node): bool
    {
        return $this->queryBuilderAnalyzer->isMatchingCall($node, 'where') || $this->queryBuilderAnalyzer->isMatchingCall($node, 'orWhere');
    }
}
