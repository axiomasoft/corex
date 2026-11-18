<?php

declare(strict_types=1);

namespace CoreX\Errors\Tooling;

use CoreX\Errors\Laravel\ErrorCatalogueFactory;
use FilesystemIterator;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ErrorCode;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Errors\Data\ErrorDescriptor;
use Packman\Errors\Exceptions\ProblemRegistryException;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/** @internal AST checks share PHP's resolved names, including aliases and group imports. */
#[Internal]
final class ErrorSourceScanner
{
    /** @return list<string> */
    public function diagnostics(string $root, ProblemRegistry $registry): array
    {
        $diagnostics = [];
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (! $file instanceof SplFileInfo) {
                continue;
            }
            $path = $file->getPathname();

            if (! $file->isFile() || $file->getExtension() !== 'php' || str_contains($path, '/vendor/') || str_contains($path, '/tests/')) {
                continue;
            }
            $nodes = $parser->parse((string) file_get_contents($path)) ?? [];
            $traverser = new NodeTraverser(new NameResolver);
            $nodes = $traverser->traverse($nodes);
            foreach ($finder->findInstanceOf($nodes, New_::class) as $node) {
                if (! $node->class instanceof Name || ! in_array($node->class->toString(), [ErrorDescriptor::class, 'CoreX\\Operations\\Errors\\Exceptions\\ApiError'], true)) {
                    continue;
                }
                $code = null;
                foreach ($node->args as $position => $argument) {
                    if (! $argument instanceof Arg) {
                        continue;
                    }

                    if (($argument->name === null && $position === 0) || in_array($argument->name?->toString(), ['code', 'errorCode'], true)) {
                        $code = $argument->value;

                        break;
                    }
                }

                if ($code instanceof String_) {
                    $diagnostics[] = 'ERROR-LITERAL '.$path.':'.$node->getStartLine();
                } elseif ($code instanceof ClassConstFetch && $code->class instanceof Name && $code->name instanceof Identifier) {
                    $constant = $code->class->toString().'::'.$code->name->toString();
                    $identity = defined($constant) ? constant($constant) : null;

                    if (! $identity instanceof ErrorCode) {
                        $diagnostics[] = 'ERROR-UNREGISTERED '.$path.':'.$node->getStartLine().' '.$constant;

                        continue;
                    }

                    try {
                        $registry->get($identity);
                    } catch (ProblemRegistryException) {
                        $diagnostics[] = 'ERROR-UNREGISTERED '.$path.':'.$node->getStartLine().' '.$constant;
                    }
                }
            }
            foreach ($finder->findInstanceOf($nodes, Enum_::class) as $enum) {
                if (! in_array(ErrorCode::class, array_map(static fn (Name $name): string => $name->toString(), $enum->implements), true)) {
                    continue;
                }
                foreach ($enum->stmts as $case) {
                    if (! $case instanceof EnumCase) {
                        continue;
                    }

                    if ($enum->namespacedName === null) {
                        throw new RuntimeException('Error enum has no resolved name.');
                    }
                    $identity = constant($enum->namespacedName->toString().'::'.$case->name->toString());

                    if (! $identity instanceof ErrorCode) {
                        continue;
                    }

                    try {
                        $registry->get($identity);
                    } catch (ProblemRegistryException) {
                        $diagnostics[] = 'ERROR-UNREGISTERED-CASE '.$path.':'.$case->getStartLine();
                    }
                }
            }
        }
        sort($diagnostics);

        return $diagnostics;
    }

    public function catalogue(string $repository): ProblemRegistry
    {
        return (new ErrorCatalogueFactory)->make($repository);
    }
}
