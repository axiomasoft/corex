<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\MethodCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Facades\Redirect;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;

final class RedirectBackToBackHelperRector extends AbstractRector
{
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [MethodCall::class, StaticCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! ($node instanceof MethodCall || $node instanceof StaticCall)) {
            return null;
        }

        if ($node instanceof MethodCall) {
            return $this->updateRedirectHelperCall($node);
        }

        return $this->updateRedirectStaticCall($node);
    }

    private function updateRedirectHelperCall(MethodCall $methodCall): ?FuncCall
    {
        if (! $this->isName($methodCall->name, 'back')) {
            return null;
        }

        if (! $methodCall->var instanceof FuncCall) {
            return null;
        }

        if ($methodCall->var->getArgs() !== []) {
            return null;
        }

        if (! $this->isName($methodCall->var->name, 'redirect')) {
            return null;
        }
        $methodCall->var->name = new Name('back');
        $methodCall->var->args = $methodCall->getArgs();

        return $methodCall->var;
    }

    private function updateRedirectStaticCall(StaticCall $staticCall): ?FuncCall
    {
        if (! $this->isName($staticCall->class, Redirect::class)) {
            return null;
        }

        if (! $this->isName($staticCall->name, 'back')) {
            return null;
        }

        return new FuncCall(new Name('back'), $staticCall->args);
    }
}
