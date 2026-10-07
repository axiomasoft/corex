<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\FuncCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use PhpParser\Node;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\FuncCall;

final class NotFilledBlankFuncCallToBlankFilledFuncCallRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [BooleanNot::class];
    }

    public function refactor(Node $node): ?FuncCall
    {
        if (! $node instanceof BooleanNot) {
            return null;
        }

        if (! $node->expr instanceof FuncCall) {
            return null;
        }

        if (! $this->isName($node->expr->name, 'filled') && ! $this->isName($node->expr->name, 'blank')) {
            return null;
        }
        $method = $this->isName($node->expr->name, 'filled') ? 'blank' : 'filled';

        return $this->nodeFactory->createFuncCall($method, $node->expr->args);
    }
}
