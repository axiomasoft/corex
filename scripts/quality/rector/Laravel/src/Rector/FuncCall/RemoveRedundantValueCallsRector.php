<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\FuncCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Type\ClosureType;
use PHPStan\Type\MixedType;

final class RemoveRedundantValueCallsRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof FuncCall) {
            return null;
        }

        if (! $node->name instanceof Name) {
            return null;
        }

        if (! $this->isName($node->name, 'value')) {
            return null;
        }
        $args = $node->getArgs();

        if (count($args) !== 1) {
            return null;
        }

        if ($this->getType($args[0]->value)->isSuperTypeOf(new ClosureType([], new MixedType, true))->no() === false) {
            return null;
        }

        return $args[0]->value;
    }
}
