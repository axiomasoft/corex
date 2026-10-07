<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\ArrayDimFetch;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Env;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\StaticCall;
use Rector\NodeTypeResolver\Node\AttributeKey;

final class EnvVariableToEnvHelperRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [ArrayDimFetch::class];
    }

    public function refactor(Node $node): ?StaticCall
    {
        if (! $node instanceof ArrayDimFetch) {
            return null;
        }

        if ($node->getAttribute(AttributeKey::IS_BEING_ASSIGNED) === true) {
            return null;
        }

        if (! $this->isName($node->var, '_ENV')) {
            return null;
        }

        if ($node->dim === null) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(Env::class, 'get', [new Arg($node->dim)]);
    }
}
