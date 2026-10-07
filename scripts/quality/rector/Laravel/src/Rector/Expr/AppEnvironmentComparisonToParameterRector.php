<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\Expr;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\App;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use PHPStan\Type\ObjectType;

final class AppEnvironmentComparisonToParameterRector extends AbstractRector
{
    /** @return list<class-string<Node>> */
    public function getNodeTypes(): array
    {
        return [FuncCall::class, Identical::class, Equal::class, NotIdentical::class, NotEqual::class];
    }

    /**/
    public function refactor(Node $node): ?Expr
    {
        if (! ($node instanceof FuncCall || $node instanceof Identical || $node instanceof Equal || $node instanceof NotIdentical || $node instanceof NotEqual)) {
            return null;
        }

        if ($node instanceof FuncCall) {
            [$methodCall, $otherNode] = $this->handleFuncCall($node);
        } else {
            [$methodCall, $otherNode] = $this->handleBinaryOp($node);
        }

        if ($methodCall === null || ! $otherNode instanceof Expr) {
            return null;
        }
        $methodName = null;

        if ($otherNode instanceof String_) {
            $methodName = $this->getMethodName($methodCall, $otherNode->value);
        }

        if ($methodName === null) {
            $methodCall->args[] = new Arg($otherNode);
        } else {
            $methodCall->name = new Identifier($methodName);
        }

        if ($node instanceof NotIdentical || $node instanceof NotEqual) {
            return new BooleanNot($methodCall);
        }

        return $methodCall;
    }

    /** @return array{0: MethodCall|StaticCall|null, 1: Expr|null} */
    private function handleFuncCall(FuncCall $funcCall): array
    {
        if (! $this->isName($funcCall->name, 'in_array')) {
            return [null, null];
        }
        $methodCall = $funcCall->getArg('needle', 0);
        $haystack = $funcCall->getArg('haystack', 1);

        if (! $haystack instanceof Arg || ! $methodCall instanceof Arg || ! $this->validMethodCall($methodCall->value)) {
            return [null, null];
        }

        return [$methodCall->value, $haystack->value];
    }

    /** @return array{0: MethodCall|StaticCall|null, 1: Expr|null} */
    private function handleBinaryOp(BinaryOp $binaryOp): array
    {
        $methodCall = array_values(array_filter([$binaryOp->left, $binaryOp->right], $this->validMethodCall(...)))[0] ?? null;
        $otherNode = array_values(array_filter([$binaryOp->left, $binaryOp->right], static fn ($node) => $node !== $methodCall))[0] ?? null;

        return [$methodCall, $otherNode];
    }

    /** @phpstan-assert-if-true MethodCall|StaticCall $node */
    private function validMethodCall(Node $node): bool
    {
        if (! $node instanceof MethodCall && ! $node instanceof StaticCall) {
            return false;
        }

        if (! $this->isName($node->name, 'environment')) {
            return false;
        }

        // make sure the method call has no arguments
        if ($node->getArgs() !== []) {
            return false;
        }

        return match (true) {
            $node instanceof MethodCall && $this->isObjectType($node->var, new ObjectType(Application::class)) => true,
            $node instanceof StaticCall && $this->isObjectType($node->class, new ObjectType(App::class)) => true,
            $node instanceof StaticCall && $this->isObjectType($node->class, new ObjectType('App')) => true,
            default => false,
        };
    }

    private function getMethodName(MethodCall|StaticCall $methodCall, string $environment): ?string
    {
        if ($methodCall instanceof MethodCall && ! $this->isObjectType($methodCall->var, new ObjectType(\Illuminate\Foundation\Application::class))) {
            return null;
        }

        return match ($environment) {
            'local' => 'isLocal',
            'production' => 'isProduction',
            default => null,
        };
    }
}
