<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\StaticCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use Illuminate\Foundation\Bus\Dispatchable;
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PHPStan\Broker\ClassNotFoundException;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Contract\Rector\ConfigurableRectorInterface;

final class DispatchToHelperFunctionsRector extends AbstractRector implements ConfigurableRectorInterface
{
    public const string PRESERVE_PATHS = 'preserve_paths';

    /** @var list<string> */
    private array $preservePaths = [];

    /** @param array<mixed> $configuration */
    public function configure(array $configuration): void
    {
        if (array_diff(array_keys($configuration), [self::PRESERVE_PATHS]) !== []) {
            throw new InvalidArgumentException('Unknown Laravel preservation option');
        }
        $paths = $configuration[self::PRESERVE_PATHS] ?? [];

        if (! is_array($paths) || ! array_is_list($paths)) {
            throw new InvalidArgumentException('Preserve paths must be a list of existing absolute paths');
        }
        $preservePaths = [];
        foreach ($paths as $path) {
            if (! is_string($path) || ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
                throw new InvalidArgumentException('Preserve paths must be existing absolute paths');
            }
            $resolved = realpath($path);

            if ($resolved === false || $resolved === DIRECTORY_SEPARATOR) {
                throw new InvalidArgumentException('Preserve path does not resolve to a bounded source path');
            }
            $preservePaths[] = $resolved;
        }
        $this->preservePaths = $preservePaths;
    }

    private function shouldPreserveFile(): bool
    {
        if ($this->preservePaths === []) {
            return false;
        }
        $filePath = $this->getFile()->getFilePath();

        return array_any($this->preservePaths, fn ($path) => $filePath === $path || str_starts_with($filePath, $path.DIRECTORY_SEPARATOR));
    }

    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [StaticCall::class];
    }

    /**/
    public function refactor(Node $node): ?Node
    {
        if ($this->shouldPreserveFile()) {
            return null;
        }

        if (! $node instanceof StaticCall) {
            return null;
        }

        if ($node->isFirstClassCallable()) {
            return null;
        }

        if (! $this->isName($node->name, 'dispatch') && ! $this->isName($node->name, 'dispatchSync')) {
            return null;
        }
        $class = $node->class;

        if (! $class instanceof Name) {
            return null;
        }
        $classReflection = $this->getClassReflection($node);

        if (! $classReflection instanceof ClassReflection) {
            return null;
        }

        if ($this->usesBusDispatchable($classReflection)) {
            if ($this->isName($node->name, 'dispatchSync')) {
                return $this->createDispatchableCall($node, 'dispatch_sync');
            }

            return $this->createDispatchableCall($node, 'dispatch');
        }

        if ($this->usesEventDispatchable($classReflection)) {
            return $this->createDispatchableCall($node, 'event');
        }

        return null;
    }

    private function getClassReflection(StaticCall $staticCall): ?ClassReflection
    {
        $type = $this->getType($staticCall->class);

        if (! $type->isObject()->yes()) {
            return null;
        }
        $objectClassNames = $type->getObjectClassNames();

        if (count($objectClassNames) !== 1) {
            return null;
        }

        try {
            return $this->reflectionProvider->getClass($objectClassNames[0]);
        } catch (ClassNotFoundException) {
        }

        return null;
    }

    private function usesBusDispatchable(ClassReflection $classReflection): bool
    {
        $traits = array_keys($classReflection->getTraits(true));

        return in_array(Dispatchable::class, $traits, true);
    }

    private function usesEventDispatchable(ClassReflection $classReflection): bool
    {
        $traits = array_keys($classReflection->getTraits(true));

        return in_array(\Illuminate\Foundation\Events\Dispatchable::class, $traits, true);
    }

    private function createDispatchableCall(StaticCall $staticCall, string $method): ?FuncCall
    {
        $class = $staticCall->class;

        if (! $class instanceof Name) {
            return null;
        }
        $className = $class->isSpecialClassName() ? $class : new FullyQualified($class);

        return $this->nodeFactory->createFuncCall($method, [new New_($className, $staticCall->args)]);
    }
}
