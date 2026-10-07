<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\BooleanNot;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Enumerable;
use PhpParser\Node;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;

final class AvoidNegatedCollectionContainsOrDoesntContainRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [BooleanNot::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof BooleanNot) {
            return null;
        }

        return $this->updateBooleanNot($node);
    }

    private function updateBooleanNot(BooleanNot $booleanNot): ?MethodCall
    {
        $expr = $booleanNot->expr;

        if (! $expr instanceof MethodCall) {
            return null;
        }

        if (! $this->isObjectType($expr->var, new ObjectType(Enumerable::class))) {
            return null;
        }
        $name = $expr->name;

        if ($this->isName($name, 'contains')) {
            $replacement = 'doesntContain';
        } elseif ($this->isName($name, 'doesntContain')) {
            $replacement = 'contains';
        } else {
            return null;
        }
        $expr->name = new Identifier($replacement);

        return $expr;
    }
}
