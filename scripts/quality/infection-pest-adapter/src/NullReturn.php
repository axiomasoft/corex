<?php

declare(strict_types=1);

namespace CoreX\Tooling\Infection;

use Infection\Mutator\Definition;
use Infection\Mutator\Mutator;
use Infection\Mutator\MutatorCategory;
use PhpParser\Node;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Return_;

/** @implements Mutator<Return_> */
final class NullReturn implements Mutator
{
    public function getName(): string
    {
        return 'NullReturn';
    }

    public static function getDefinition(): Definition
    {
        return new Definition(
            'Replace a null return value with false to verify the observable null contract.',
            MutatorCategory::ORTHOGONAL_REPLACEMENT,
            null,
            '- return null;'."\n".'+ return false;',
        );
    }

    public function canMutate(Node $node): bool
    {
        return $node instanceof Return_
            && $node->expr instanceof ConstFetch
            && $node->expr->name->toLowerString() === 'null';
    }

    /** @return iterable<Return_> */
    public function mutate(Node $node): iterable
    {
        yield new Return_(new ConstFetch(new Name('false')));
    }
}
