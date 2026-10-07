<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\FuncCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Sleep;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Expression;

final class SleepFuncToSleepStaticCallRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if (! $node->expr instanceof FuncCall) {
            return null;
        }

        if (! $this->isName($node->expr->name, 'sleep') && ! $this->isName($node->expr->name, 'usleep')) {
            return null;
        }
        $method = $this->isName($node->expr->name, 'sleep') ? 'sleep' : 'usleep';
        $node->expr = $this->nodeFactory->createStaticCall(Sleep::class, $method, $node->expr->args);

        return $node;
    }
}
