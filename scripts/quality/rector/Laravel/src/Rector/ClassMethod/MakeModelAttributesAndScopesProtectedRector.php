<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\ClassMethod;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use CoreX\Tooling\Rector\Laravel\NodeAnalyzer\ScopeAnalyzer;
use Illuminate\Database\Eloquent\Casts\Attribute;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\ObjectType;
use Rector\PHPStan\ScopeFetcher;
use Rector\Privatization\NodeManipulator\VisibilityManipulator;

final class MakeModelAttributesAndScopesProtectedRector extends AbstractRector
{
    public function __construct(private readonly VisibilityManipulator $visibilityManipulator, private readonly ScopeAnalyzer $scopeAnalyzer) {}

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [ClassMethod::class];
    }

    /**/
    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof ClassMethod) {
            return null;
        }
        $scope = ScopeFetcher::fetch($node);

        if ($this->shouldSkipNode($node, $scope)) {
            return null;
        }
        $this->visibilityManipulator->makeProtected($node);

        return $node;
    }

    private function shouldSkipNode(ClassMethod $classMethod, Scope $scope): bool
    {
        if ($classMethod->isProtected() || $classMethod->isStatic()) {
            return true;
        }

        if (! $this->isAttributeMethod($classMethod) && ! $this->scopeAnalyzer->isScopeMethod($classMethod)) {
            return true;
        }
        $classReflection = $scope->getClassReflection();

        if (! $classReflection instanceof ClassReflection || $classReflection->isAnonymous()) {
            return true;
        }

        if ($classReflection->getParentClass() instanceof ClassReflection && $classReflection->getParentClass()->hasMethod($this->getName($classMethod)) && $classReflection->getParentClass()->getMethod($this->getName($classMethod), $scope)->isPublic()) {
            return true;
        }

        return ! $classReflection->isTrait() && ! $classReflection->is('Illuminate\Database\Eloquent\Model');
    }

    private function isAttributeMethod(ClassMethod $classMethod): bool
    {
        $name = $this->getName($classMethod);

        if ((bool) preg_match('/^[gs]et.+Attribute$/', $name)) {
            return true;
        }

        if (! $classMethod->returnType instanceof Node) {
            return false;
        }

        return $this->isObjectType($classMethod->returnType, new ObjectType(Attribute::class));
    }
}
