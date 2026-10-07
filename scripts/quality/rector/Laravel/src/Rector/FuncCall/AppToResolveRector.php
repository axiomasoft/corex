<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\FuncCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;

final class AppToResolveRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [FuncCall::class];
    }

    public function refactor(Node $node): ?FuncCall
    {
        if (! $node instanceof FuncCall) {
            return null;
        }

        if (! $this->isName($node, 'app')) {
            return null;
        }
        $abstract = $node->getArg('abstract', 0);

        if ($abstract === null) {
            return null;
        }

        if ($this->getType($abstract->value)->isNull()->yes()) {
            return null;
        }
        $node->name = new Name('resolve');

        return $node;
    }
}
