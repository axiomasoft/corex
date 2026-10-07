<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\Assign;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use CoreX\Tooling\Rector\Laravel\NodeFactory\AppAssignFactory;
use CoreX\Tooling\Rector\Laravel\ValueObject\ServiceNameTypeAndVariableName;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Validation\Factory;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Nop;
use PHPStan\Type\ObjectType;
use Rector\Comments\NodeDocBlock\DocBlockUpdater;
use Rector\PhpParser\Node\Value\ValueResolver;

final class CallOnAppArrayAccessToStandaloneAssignRector extends AbstractRector
{
    /**
     * @var ServiceNameTypeAndVariableName[]
     */
    private array $serviceNameTypeAndVariableNames = [];

    public function __construct(private readonly AppAssignFactory $appAssignFactory, private readonly DocBlockUpdater $docBlockUpdater, private readonly ValueResolver $valueResolver)
    {
        $this->serviceNameTypeAndVariableNames[] = new ServiceNameTypeAndVariableName('validator', Factory::class, 'validationFactory');
    }

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [Expression::class];
    }

    /**     * @return array<int, Node>|null
     */
    public function refactor(Node $node): ?array
    {
        if (! $node instanceof Expression) {
            return null;
        }

        if (! $node->expr instanceof Assign) {
            return null;
        }

        if (! $node->expr->expr instanceof MethodCall) {
            return null;
        }
        $methodCall = $node->expr->expr;

        if (! $methodCall->var instanceof ArrayDimFetch) {
            return null;
        }
        $arrayDimFetch = $methodCall->var;

        if (! $this->isObjectType($arrayDimFetch->var, new ObjectType(Application::class))) {
            return null;
        }
        $arrayDimFetchDim = $methodCall->var->dim;

        if (! $arrayDimFetchDim instanceof Expr) {
            return null;
        }
        foreach ($this->serviceNameTypeAndVariableNames as $serviceNameTypeAndVariableName) {
            if (! $this->valueResolver->isValue($arrayDimFetchDim, $serviceNameTypeAndVariableName->getServiceName())) {
                continue;
            }
            $assignExpression = $this->appAssignFactory->createAssignExpression($serviceNameTypeAndVariableName, $methodCall->var);
            $this->docBlockUpdater->updateRefactoredNodeWithPhpDocInfo($assignExpression);
            $methodCall->var = new Variable($serviceNameTypeAndVariableName->getVariableName());

            // the nop is a workaround because the docs of the first node are somehow stripped away
            // this will add a newline but the docs will be preserved
            return [new Nop, $assignExpression, $node];
        }

        return null;
    }
}
