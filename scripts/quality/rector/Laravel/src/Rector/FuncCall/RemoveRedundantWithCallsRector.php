<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\FuncCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

final class RemoveRedundantWithCallsRector extends AbstractRector
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

        if (! $this->isName($node->name, 'with')) {
            return null;
        }
        $args = $node->getArgs();

        if (count($args) < 1 || count($args) > 2) {
            return null;
        }

        if (count($args) === 2) {
            $secondArgumentType = $this->getType($args[1]->value);

            if ($secondArgumentType->isCallable()->no() === false) {
                return null;
            }
        }

        return $args[0]->value;
    }
}
