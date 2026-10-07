<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\MethodCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Traits\Conditionable;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;

final class ReverseConditionableMethodCallRector extends AbstractRector
{
    private const string CONDITIONABLE_TRAIT = Conditionable::class;

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?MethodCall
    {
        if (! $node instanceof MethodCall) {
            return null;
        }

        if (! $this->isObjectType($node->var, new ObjectType(self::CONDITIONABLE_TRAIT))) {
            return null;
        }

        if (! $this->isNames($node->name, ['when', 'unless'])) {
            return null;
        }

        if ($node->isFirstClassCallable()) {
            return null;
        }

        if ($node->getArgs() === []) {
            return null;
        }
        $arg = $node->getArgs()[0];

        if (! $node->name instanceof Identifier) {
            return null;
        }

        if ($arg->value instanceof BooleanNot) {
            $node->args[0] = new Arg($arg->value->expr);
            $name = $node->name->toString() === 'when' ? 'unless' : 'when';
            $node->name = new Identifier($name);

            return $node;
        }

        return null;
    }
}
