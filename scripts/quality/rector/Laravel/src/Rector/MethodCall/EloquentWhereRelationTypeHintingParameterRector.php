<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\MethodCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use CoreX\Tooling\Rector\Laravel\NodeAnalyzer\QueryBuilderAnalyzer;
use Illuminate\Contracts\Database\Query\Builder;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;

final class EloquentWhereRelationTypeHintingParameterRector extends AbstractRector
{
    /**
     * @var string[]
     */
    private const array METHODS = ['whereHas', 'orWhereHas', 'whereDoesntHave', 'orWhereDoesntHave', 'whereHasMorph', 'orWhereHasMorph', 'whereDoesntHaveMorph', 'orWhereDoesntHaveMorph'];

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

        if ($this->isWhereRelationMethodWithClosureOrArrowFunction($node)) {
            return $this->changeClosureParamType($node);
        }

        return null;
    }

    private function isWhereRelationMethodWithClosureOrArrowFunction(MethodCall|StaticCall $node): bool
    {
        if (! $this->expectedObjectTypeAndMethodCall($node)) {
            return false;
        }
        $position = $this->getPosition($node);

        return ($node->getArgs()[$position]->value ?? null) instanceof Closure || ($node->getArgs()[$position]->value ?? null) instanceof ArrowFunction;
    }

    private function changeClosureParamType(MethodCall|StaticCall $node): ?Node
    {
        $position = $this->getPosition($node);
        /** @var ArrowFunction|Closure $closure */
        $closure = $node->getArgs()[$position]->value;

        if (! isset($closure->getParams()[0])) {
            return null;
        }
        $param = $closure->getParams()[0];

        if ($param->type instanceof Name) {
            return null;
        }
        $param->type = new FullyQualified(Builder::class);

        return $node;
    }

    private function expectedObjectTypeAndMethodCall(MethodCall|StaticCall $node): bool
    {
        return array_any(self::METHODS, fn ($method) => $this->queryBuilderAnalyzer->isMatchingCall($node, $method));
    }

    private function getPosition(MethodCall|StaticCall $node): int
    {
        // Morph methods have the closure in the 3rd position, others use the 2nd.
        return $this->isNames($node->name, ['whereHasMorph', 'orWhereHasMorph', 'whereDoesntHaveMorph', 'orWhereDoesntHaveMorph']) ? 2 : 1;
    }
}
