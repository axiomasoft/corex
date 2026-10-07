<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\FuncCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;

final class NowFuncWithStartOfDayMethodCallToTodayFuncRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?FuncCall
    {
        if (! $node instanceof MethodCall) {
            return null;
        }

        if (! $this->isName($node->name, 'startOfDay')) {
            return null;
        }

        if (! $node->var instanceof FuncCall) {
            return null;
        }

        if (! $this->isName($node->var, 'now')) {
            return null;
        }

        return $this->nodeFactory->createFuncCall('today');
    }
}
