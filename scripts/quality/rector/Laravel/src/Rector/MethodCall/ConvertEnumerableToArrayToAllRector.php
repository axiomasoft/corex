<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\MethodCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Enumerable;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use Rector\PHPStan\ScopeFetcher;

final class ConvertEnumerableToArrayToAllRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class, NullsafeMethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! ($node instanceof MethodCall || $node instanceof NullsafeMethodCall)) {
            return null;
        }

        if (! $this->isName($node->name, 'toArray')) {
            return null;
        }

        if (! $this->isObjectType($node->var, new ObjectType(Enumerable::class))) {
            return null;
        }
        $scope = ScopeFetcher::fetch($node);
        $type = TypeCombinator::removeNull(
            // This HAS to use the $scope->getType() as opposed to $this->getType()
            // because it's not getting the proper type from the Larastan extensions
            $scope->getType($node->var),
        );
        $valueType = $type->getTemplateType(Enumerable::class, 'TValue');

        if (! new ObjectType(Arrayable::class)->isSuperTypeOf($valueType)->no()) {
            return null;
        }
        $node->name = new Identifier('all');

        return $node;
    }
}
