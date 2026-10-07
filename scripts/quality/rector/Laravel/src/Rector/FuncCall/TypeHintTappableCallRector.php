<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\FuncCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Support\Traits\Tappable;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Param;
use PHPStan\Type\ObjectType;
use Rector\NodeTypeResolver\TypeComparator\TypeComparator;
use Rector\PHPStanStaticTypeMapper\Enum\TypeKind;
use Rector\StaticTypeMapper\StaticTypeMapper;

final class TypeHintTappableCallRector extends AbstractRector
{
    private const string TAPPABLE_TRAIT = Tappable::class;

    public function __construct(private readonly TypeComparator $typeComparator, private readonly StaticTypeMapper $staticTypeMapper) {}

    public function getNodeTypes(): array
    {
        return [MethodCall::class, FuncCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! ($node instanceof MethodCall || $node instanceof FuncCall)) {
            return null;
        }

        if (! $this->isName($node->name, 'tap')) {
            return null;
        }

        if ($node->isFirstClassCallable()) {
            return null;
        }

        if ($node instanceof MethodCall && $node->getArgs() !== []) {
            return $this->refactorMethodCall($node);
        }

        if (count($node->getArgs()) < 2 || ! $node->getArgs()[1]->value instanceof Closure) {
            return null;
        }
        $closure = $node->getArgs()[1]->value;

        if ($closure->getParams() === []) {
            return null;
        }
        $this->refactorParameter($closure->getParams()[0], $node->getArgs()[0]->value);

        return $node;
    }

    private function refactorParameter(Param $param, Node $node): void
    {
        $nodePhpStanType = $this->nodeTypeResolver->getType($node);

        // already set → no change
        if ($param->type instanceof Node) {
            $currentParamType = $this->staticTypeMapper->mapPhpParserNodePHPStanType($param->type);

            if ($this->typeComparator->areTypesEqual($currentParamType, $nodePhpStanType)) {
                return;
            }
        }
        $paramTypeNode = $this->staticTypeMapper->mapPHPStanTypeToPhpParserNode($nodePhpStanType, TypeKind::PARAM);
        $param->type = $paramTypeNode;
    }

    private function refactorMethodCall(MethodCall $methodCall): ?MethodCall
    {
        if (! $this->isTappableCall($methodCall)) {
            return null;
        }

        if (! $methodCall->getArgs()[0]->value instanceof Closure) {
            return null;
        }
        $closure = $methodCall->getArgs()[0]->value;

        if ($closure->getParams() === []) {
            return null;
        }
        $this->refactorParameter($closure->getParams()[0], $methodCall->var);

        return $methodCall;
    }

    private function isTappableCall(MethodCall $methodCall): bool
    {
        return $this->isObjectType($methodCall->var, new ObjectType(self::TAPPABLE_TRAIT));
    }
}
