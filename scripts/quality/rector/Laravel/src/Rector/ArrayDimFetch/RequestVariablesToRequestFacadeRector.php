<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\ArrayDimFetch;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use CoreX\Tooling\Rector\Laravel\NodeVisitor\ArrayDimFetchContextNodeVisitor;
use CoreX\Tooling\Rector\Laravel\ValueObject\ReplaceRequestKeyAndMethodValue;
use Illuminate\Support\Facades\Request;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Scalar;
use PhpParser\Node\Scalar\String_;
use Rector\NodeTypeResolver\Node\AttributeKey;

final class RequestVariablesToRequestFacadeRector extends AbstractRector
{
    public function getNodeTypes(): array
    {
        return [ArrayDimFetch::class, Variable::class, Isset_::class];
    }

    public function refactor(Node $node): StaticCall|NotIdentical|null
    {
        if (! ($node instanceof ArrayDimFetch || $node instanceof Variable || $node instanceof Isset_)) {
            return null;
        }

        // skip direct assignment targets: $_POST = $data, $_POST['key'] = $value
        if ($node->getAttribute(AttributeKey::IS_BEING_ASSIGNED) === true) {
            return null;
        }

        // skip inner nodes of superglobal assignments (e.g. the $_POST Variable
        // inside $_POST['key'] = ...) and non-assignment contexts like isset/unset
        if ($node->getAttribute(ArrayDimFetchContextNodeVisitor::IS_IN_SUPERGLOBAL_ASSIGN) === true) {
            return null;
        }

        if ($node instanceof Variable) {
            if ($node->getAttribute(ArrayDimFetchContextNodeVisitor::IS_INSIDE_ARRAY_DIM_FETCH_WITH_DIM_NOT_SCALAR) === true) {
                return null;
            }

            return $this->processVariable($node);
        }

        if ($node instanceof Isset_) {
            return $this->processIsset($node);
        }
        $replaceValue = $this->findAllKeys($node);

        if ($replaceValue instanceof ReplaceRequestKeyAndMethodValue) {
            return $this->nodeFactory->createStaticCall(Request::class, $replaceValue->getMethod(), [new Arg(new String_($replaceValue->getKey()))]);
        }

        return $replaceValue;
    }

    public function findAllKeys(ArrayDimFetch $arrayDimFetch): ?ReplaceRequestKeyAndMethodValue
    {
        if (! $arrayDimFetch->dim instanceof Scalar) {
            return null;
        }
        $value = $this->getType($arrayDimFetch->dim)->getConstantScalarValues()[0] ?? null;

        if ($value === null) {
            return null;
        }

        if ($arrayDimFetch->var instanceof ArrayDimFetch) {
            $replaceValue = $this->findAllKeys($arrayDimFetch->var);

            if (! $replaceValue instanceof ReplaceRequestKeyAndMethodValue) {
                return $replaceValue;
            }

            return new ReplaceRequestKeyAndMethodValue(implode('.', [$replaceValue->getKey(), $value]), $replaceValue->getMethod());
        }

        if ($this->isNames($arrayDimFetch->var, ['_GET', '_POST', '_REQUEST'])) {
            if (! $arrayDimFetch->var instanceof Variable) {
                return null;
            }
            $method = match ($arrayDimFetch->var->name) {
                '_GET' => 'query',
                '_POST' => 'post',
                '_REQUEST' => 'input',
                default => null,
            };

            if ($method === null) {
                return null;
            }

            return new ReplaceRequestKeyAndMethodValue((string) $value, $method);
        }

        return null;
    }

    private function getGetterMethodName(Variable $variable): ?string
    {
        return match ($variable->name) {
            '_GET' => 'query',
            '_POST' => 'post',
            '_REQUEST' => 'input',
            default => null,
        };
    }

    private function processIsset(Isset_ $isset): StaticCall|NotIdentical|null
    {
        if (count($isset->vars) < 1) {
            return null;
        }
        $var = $isset->vars[0];

        if (! $var instanceof ArrayDimFetch) {
            return null;
        }

        if (! $var->var instanceof Variable) {
            return null;
        }
        $method = $this->getGetterMethodName($var->var);

        if ($method === null) {
            return null;
        }

        if (! $var->dim instanceof Expr) {
            return null;
        }
        $replaceValue = $this->findAllKeys($var);

        if (! $replaceValue instanceof ReplaceRequestKeyAndMethodValue) {
            return $replaceValue;
        }
        $args = [new Arg(new String_($replaceValue->getKey()))];

        if ($method === 'input') {
            return $this->nodeFactory->createStaticCall(Request::class, 'exists', $args);
        }

        return new NotIdentical($this->nodeFactory->createStaticCall(Request::class, $method, $args), $this->nodeFactory->createNull());
    }

    private function processVariable(Variable $variable): ?StaticCall
    {
        $method = match ($variable->name) {
            '_GET' => 'query',
            '_POST' => 'post',
            '_REQUEST' => 'all',
            default => null,
        };

        if ($method === null) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(Request::class, $method);
    }
}
