<?php

declare(strict_types=1);

namespace CoreX\Tooling\Infection;

use Infection\Mutator\Definition;
use Infection\Mutator\Mutator;
use Infection\Mutator\MutatorCategory;
use PhpParser\Node;

/** @implements Mutator<Node\Param> */
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
        return $node instanceof Node\Param && $node->default instanceof Node\Expr\New_
            && array_any($node->default->args, static fn (Node $arg): bool => $arg instanceof Node\Arg && $arg->value instanceof Node\Scalar\String_);
    }

    /** @return iterable<Node\Param> */
    public function mutate(Node $node): iterable
    {
        if (! $node->default instanceof Node\Expr\New_) {
            return;
        }
        foreach ($node->default->args as $index => $arg) {
            if (! $arg instanceof Node\Arg || ! $arg->value instanceof Node\Scalar\String_) {
                continue;
            }
            $mutant = clone $node;
            $mutant->default = clone $node->default;
            $changed = clone $arg;
            $changed->value = new Node\Scalar\String_(match ($arg->value->value) {
                '[]' => '{}',
                '{}' => '[]',
                default => $arg->value->value.'_',
            });
            $mutant->default->args[$index] = $changed;
            yield $mutant;
        }
    }
}
