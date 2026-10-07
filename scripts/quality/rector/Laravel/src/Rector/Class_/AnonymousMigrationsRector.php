<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\Class_;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Database\Migrations\Migration;
use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Type\ObjectType;
use Rector\NodeAnalyzer\ClassAnalyzer;

/**
 * @changelog https://github.com/laravel/framework/pull/36906
 * @changelog https://github.com/laravel/framework/pull/37352
 */
final class AnonymousMigrationsRector extends AbstractRector
{
    public function __construct(private readonly ClassAnalyzer $classAnalyzer) {}

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Class_::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof Class_) {
            return null;
        }

        if (! $this->isObjectType($node, new ObjectType(Migration::class))) {
            return null;
        }

        if ($node->isAbstract()) {
            return null;
        }

        if ($this->classAnalyzer->isAnonymousClass($node)) {
            return null;
        }
        $node->name = null;

        return new Return_(new New_($node));
    }
}
