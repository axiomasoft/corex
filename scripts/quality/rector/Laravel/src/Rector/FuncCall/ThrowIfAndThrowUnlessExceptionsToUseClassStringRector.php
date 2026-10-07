<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\FuncCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;

final class ThrowIfAndThrowUnlessExceptionsToUseClassStringRector extends AbstractRector
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

        if (! $this->isNames($node, ['throw_if', 'throw_unless'])) {
            return null;
        }

        if (count($node->args) !== 2 || ! $node->args[1] instanceof Arg) {
            return null;
        }
        $exception = $node->args[1]->value;

        if (! $exception instanceof New_) {
            return null;
        }
        $class = $exception->class;

        if (! $class instanceof Name) {
            return null;
        }
        // convert the class to a class string
        $node->args[1] = new Arg(new ClassConstFetch($class, 'class'));
        $node->args = [...$node->args, ...$exception->getArgs()];

        return $node;
    }
}
