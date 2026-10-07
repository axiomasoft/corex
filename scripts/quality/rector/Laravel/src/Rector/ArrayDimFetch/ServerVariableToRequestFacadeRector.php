<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\ArrayDimFetch;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use CoreX\Tooling\Rector\Laravel\NodeVisitor\ArrayDimFetchContextNodeVisitor;
use Illuminate\Support\Facades\Request;
use InvalidArgumentException;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\StaticCall;
use Rector\Contract\Rector\ConfigurableRectorInterface;

final class ServerVariableToRequestFacadeRector extends AbstractRector implements ConfigurableRectorInterface
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

    public function getNodeTypes(): array
    {
        return [ArrayDimFetch::class];
    }

    public function refactor(Node $node): ?StaticCall
    {
        if ($this->shouldPreserveFile()) {
            return null;
        }

        if (! $node instanceof ArrayDimFetch) {
            return null;
        }

        if (! $this->isName($node->var, '_SERVER')) {
            return null;
        }

        if (! $node->dim instanceof Expr) {
            return null;
        }

        if ($node->getAttribute(ArrayDimFetchContextNodeVisitor::IS_IN_SUPERGLOBAL_ASSIGN) === true) {
            return null;
        }

        return $this->nodeFactory->createStaticCall(Request::class, 'server', [new Arg($node->dim)]);
    }
}
