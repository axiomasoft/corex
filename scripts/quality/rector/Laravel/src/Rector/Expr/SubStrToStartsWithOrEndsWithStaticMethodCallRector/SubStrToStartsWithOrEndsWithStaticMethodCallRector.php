<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\Expr\SubStrToStartsWithOrEndsWithStaticMethodCallRector;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use Rector\PhpParser\Node\Value\ValueResolver;

final class SubStrToStartsWithOrEndsWithStaticMethodCallRector extends AbstractRector
{
    public function __construct(private readonly ValueResolver $valueResolver) {}

    public function getNodeTypes(): array
    {
        return [Expr::class];
    }

    public function refactor(Node $node): ?StaticCall
    {
        if (! $node instanceof Expr) {
            return null;
        }

        if (! $node instanceof Identical && ! $node instanceof Equal) {
            return null;
        }
        /** @var FuncCall|null $functionCall */
        $functionCall = array_values(array_filter([$node->left, $node->right], fn ($node) => $node instanceof FuncCall && $this->isName($node, 'substr')))[0] ?? null;

        if (! $functionCall instanceof FuncCall) {
            return null;
        }
        /** @var Expr $otherNode */
        $otherNode = array_values(array_filter([$node->left, $node->right], static fn ($node) => $node !== $functionCall))[0] ?? null;

        // get the function call second argument value
        if (count($functionCall->getArgs()) < 2) {
            return null;
        }
        $secondArgument = $this->valueResolver->getValue($functionCall->getArgs()[1]->value);

        if (! is_int($secondArgument)) {
            return null;
        }

        if ($secondArgument < 0 && isset($functionCall->getArgs()[2])) {
            return null;
        }
        $methodName = $this->getStaticMethodName($secondArgument);

        if ($methodName === null) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(Str::class, $methodName, [$functionCall->getArgs()[0]->value, $otherNode]);
    }

    protected function getStaticMethodName(int $secondArgument): ?string
    {
        if ($secondArgument === 0) {
            return 'startsWith';
        }

        if ($secondArgument < 0) {
            return 'endsWith';
        }

        return null;
    }
}
