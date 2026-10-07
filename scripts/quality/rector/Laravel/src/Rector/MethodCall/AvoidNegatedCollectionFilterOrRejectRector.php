<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\MethodCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Enumerable;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast\Bool_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;

final class AvoidNegatedCollectionFilterOrRejectRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof MethodCall) {
            return null;
        }

        return $this->updateFilterOrRejectCall($node);
    }

    private function updateFilterOrRejectCall(MethodCall $methodCall): ?MethodCall
    {
        if (! $this->isObjectType($methodCall->var, new ObjectType(Enumerable::class))) {
            return null;
        }

        if (! $this->isNames($methodCall->name, ['filter', 'reject'])) {
            return null;
        }
        $args = $methodCall->getArgs();

        if (count($args) !== 1) {
            return null;
        }
        $arg = $args[0];
        $argValue = $arg->value;

        if (! $argValue instanceof ArrowFunction) {
            return null;
        }
        $return = $argValue->expr;

        if (! $return instanceof BooleanNot) {
            return null;
        }
        $methodCall->name = new Identifier($this->isName($methodCall->name, 'filter') ? 'reject' : 'filter');
        // Since negation implicitly casts to boolean, we need to replace it with an
        // explicit cast - unless the value is already a boolean.
        $returnExpr = $return->expr;
        $argValue->expr = $this->getType($returnExpr)->isBoolean()->yes() ? $returnExpr : new Bool_($returnExpr);
        $argValue->returnType = new Identifier('bool');

        return $methodCall;
    }
}
