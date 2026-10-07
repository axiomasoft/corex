<?php

declare(strict_types=1);

namespace CoreX\Tooling\Infection;

use Infection\Mutator\Definition;
use Infection\Mutator\Mutator;
use Infection\Mutator\MutatorCategory;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\String_;

/** @implements Mutator<Param> */
final class DefaultObjectArgument implements Mutator
{
    public function getName(): string
    {
        return 'DefaultObjectArgument';
    }

    public static function getDefinition(): Definition
    {
        return new Definition(
            'Perturb a literal string passed to an executable object default without changing its declared type.',
            MutatorCategory::ORTHOGONAL_REPLACEMENT,
            null,
            '- public JsonDocument $metadata = new JsonDocument(\'[]\')'."\n".'+ public JsonDocument $metadata = new JsonDocument(\'{}\')',
        );
    }

    public function canMutate(Node $node): bool
    {
        return $node instanceof Param && $node->default instanceof New_
            && array_any($node->default->args, static fn (Node $arg): bool => $arg instanceof Arg && $arg->value instanceof String_);
    }

    /** @return iterable<Param> */
    public function mutate(Node $node): iterable
    {
        if (! $node->default instanceof New_) {
            return;
        }
        foreach ($node->default->args as $index => $arg) {
            if (! $arg instanceof Arg || ! $arg->value instanceof String_) {
                continue;
            }
            $mutant = clone $node;
            $mutant->default = clone $node->default;
            $changed = clone $arg;
            $changed->value = new String_(match ($arg->value->value) {
                '[]' => '{}',
                '{}' => '[]',
                default => $arg->value->value.'_',
            });
            $mutant->default->args[$index] = $changed;
            yield $mutant;
        }
    }
}
