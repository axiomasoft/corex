<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\MethodCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Enumerable;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;

final class UnaliasCollectionMethodsRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof MethodCall) {
            return null;
        }

        return $this->updateMethodCall($node);
    }

    private function updateMethodCall(MethodCall $methodCall): ?MethodCall
    {
        if (! $this->isObjectType($methodCall->var, new ObjectType(Enumerable::class))) {
            return null;
        }
        $name = $methodCall->name;

        if ($this->isName($name, 'some')) {
            $replacement = 'contains';
        } elseif ($this->isName($name, 'average')) {
            $replacement = 'avg';
        } elseif ($this->isName($name, 'unlessEmpty')) {
            $replacement = 'whenNotEmpty';
        } elseif ($this->isName($name, 'unlessNotEmpty')) {
            $replacement = 'whenEmpty';
        } else {
            return null;
        }
        $methodCall->name = new Identifier($replacement);

        return $methodCall;
    }
}
