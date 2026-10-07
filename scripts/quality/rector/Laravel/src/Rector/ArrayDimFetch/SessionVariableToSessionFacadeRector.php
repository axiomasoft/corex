<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\ArrayDimFetch;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Facades\Session;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Unset_;

final class SessionVariableToSessionFacadeRector extends AbstractRector
{
    private const string IS_INSIDE_ARRAY_DIM_FETCH_WITH_DIM_NOT_EXPR = 'is_inside_array_dim_fetch_with_dim_not_expr';

    public function getNodeTypes(): array
    {
        return [Isset_::class, Unset_::class, ArrayDimFetch::class, Assign::class, FuncCall::class, Variable::class];
    }

    public function refactor(Node $node): StaticCall|Expression|null
    {
        if (! ($node instanceof Isset_ || $node instanceof Unset_ || $node instanceof ArrayDimFetch || $node instanceof Assign || $node instanceof FuncCall || $node instanceof Variable)) {
            return null;
        }

        if ($node instanceof ArrayDimFetch) {
            return $this->processDimFetch($node);
        }

        if ($node instanceof FuncCall) {
            return $this->processFunction($node);
        }

        if ($node instanceof Isset_) {
            return $this->processIsset($node);
        }

        if ($node instanceof Unset_) {
            $return = $this->processUnset($node);

            if ($return instanceof StaticCall) {
                return new Expression($return);
            }

            return $return;
        }

        if ($node instanceof Variable) {
            return $this->processVariable($node);
        }

        return $this->processAssign($node);
    }

    public function processDimFetch(ArrayDimFetch $arrayDimFetch): ?StaticCall
    {
        if (! $this->isName($arrayDimFetch->var, '_SESSION')) {
            return null;
        }

        if (! $arrayDimFetch->dim instanceof Expr) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(Session::class, 'get', [new Arg($arrayDimFetch->dim)]);
    }

    private function processAssign(Assign $assign): ?StaticCall
    {
        $dimFetch = $assign->var;

        if (! $dimFetch instanceof ArrayDimFetch || ! $this->isName($dimFetch->var, '_SESSION')) {
            return null;
        }

        if (! $dimFetch->dim instanceof Expr) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(Session::class, 'put', [new Arg($dimFetch->dim), new Arg($assign->expr)]);
    }

    private function processFunction(FuncCall $funcCall): ?StaticCall
    {
        if (! $this->isNames($funcCall, ['session_regenerate_id', 'session_unset', 'session_destroy', 'session_start'])) {
            return null;
        }
        $method = $this->getName($funcCall);
        $replacementMethod = match ($method) {
            'session_regenerate_id' => 'regenerate',
            'session_unset' => 'flush',
            'session_destroy' => 'destroy',
            'session_start' => 'start',
            default => null,
        };

        if ($replacementMethod === null) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(Session::class, $replacementMethod);
    }

    private function processIsset(Isset_ $isset): ?StaticCall
    {
        if (count($isset->vars) < 1) {
            return null;
        }
        $var = $isset->vars[0];

        if (! $var instanceof ArrayDimFetch) {
            return null;
        }

        if (! $this->isName($var->var, '_SESSION')) {
            return null;
        }

        if (! $var->dim instanceof Expr) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(Session::class, 'has', [new Arg($var->dim)]);
    }

    private function processUnset(Unset_ $unset): ?StaticCall
    {
        if (count($unset->vars) < 1) {
            return null;
        }
        $var = $unset->vars[0];

        if (! $var instanceof ArrayDimFetch) {
            return null;
        }

        if (! $this->isName($var->var, '_SESSION')) {
            return null;
        }

        if (! $var->dim instanceof Expr) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(Session::class, 'forget', [new Arg($var->dim)]);
    }

    private function processVariable(Variable $variable): ?StaticCall
    {
        if ($variable->getAttribute(self::IS_INSIDE_ARRAY_DIM_FETCH_WITH_DIM_NOT_EXPR) === true) {
            return null;
        }

        if (! $this->isName($variable, '_SESSION')) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(Session::class, 'all');
    }
}
