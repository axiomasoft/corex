#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Quality\UnusedPublic;

use CoreX\Attributes\Api;
use CoreX\Attributes\Spi;
use DateTimeImmutable;
use FilesystemIterator;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Orchestra\Testbench\TestCase;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

require dirname(__DIR__, 2).'/vendor/autoload.php';
ini_set('memory_limit', '1G');

// Declaration/reference analysis follows typed receivers, inherited contracts,
// container resolution and explicit callable registrations. Arbitrary dynamic
// PHP cannot supply proof from a matching method name: its exact entrypoint
// needs reviewed, hash-bound invocation evidence in public-entrypoints.json.
// --report exposes source facts for that review; this gate never refreshes roots.
final readonly class MethodDeclaration
{
    public function __construct(
        public string $class,
        public Stmt\ClassMethod $node,
        public string $source,
        public string $hash,
        public ?string $export,
    ) {}

    public function symbol(): string
    {
        return $this->class.'::'.$this->node->name->toString();
    }
}

final class UsageScanner
{
    /** @var array<string, array{node: Stmt\ClassLike, parents: list<string>, properties: array<string, list<string>>, methods: array<string, MethodDeclaration>}> */
    private array $classes = [];

    /** @var array<string, MethodDeclaration> */
    private array $public = [];

    /** @var array<string, list<string>> */
    private array $usage = [];

    /** @var array<string, array<string, string>> */
    private array $imports = [];

    /** @var array<string, array<string, string>> */
    private array $classImports = [];

    /** @var array<string, array<string, Expr>> */
    private array $constants = [];

    /** @var array<string, array<string, array{doc: string, source: string, parameter: ?string}>> */
    private array $propertyDocs = [];

    /** @var array<string, array{node: Stmt\Function_, source: string}> */
    private array $functions = [];

    /** @var array<string, array<string, list<string>>> */
    private array $testProperties = [];

    private string $currentFile = '';

    /** @var array<string, list<string>> */
    private array $ancestors = [];

    /** @var array<string, list<string>> */
    private array $dispatch = [];

    /** @var list<string> */
    private array $errors = [];

    public function __construct(private readonly string $root) {}

    /** @return array{methods: list<array{symbol: string, source: string, source_sha256: string, export: ?string, used_at: list<string>, contracts: list<string>, parents: list<string>}>, errors: list<string>} */
    public function scan(): array
    {
        $parser = new ParserFactory()->createForNewestSupportedVersion();
        $trees = [];
        $jsonFiles = [];
        foreach ($this->files() as $file) {
            $source = file_get_contents($this->root.'/'.$file);

            if ($source === false) {
                $this->errors[] = 'SOURCE-READ '.$file;

                continue;
            }

            if (str_ends_with($file, '.json')) {
                $jsonFiles[$file] = $source;

                continue;
            }

            try {
                $tree = $parser->parse($source) ?? [];
                $tree = array_values(new NodeTraverser(new NameResolver)->traverse($tree));
                $trees[$file] = $tree;
                $this->declarations($tree, $file, hash('sha256', $source));
            } catch (Throwable $error) {
                $this->errors[] = 'SOURCE-PARSE '.$file.': '.$error->getMessage();
            }
        }
        foreach ($this->propertyDocs as $class => $properties) {
            foreach ($properties as $property => $doc) {
                if (($this->classes[$class]['properties'][$property] ?? []) === []) {
                    $this->classes[$class]['properties'][$property] = $this->docTypes($doc['doc'], $doc['parameter'] === null ? 'var' : 'param', $doc['source'], $class, $doc['parameter']);
                }
            }
        }
        foreach (array_keys($this->classes) as $class) {
            foreach ($this->relatives($class) as $receiver) {
                $this->dispatch[$receiver] ??= [];
                $this->dispatch[$receiver] = array_values(array_unique([...$this->dispatch[$receiver], ...$this->relatives($class)]));
            }
        }
        foreach ($trees as $file => $tree) {
            $this->currentFile = $file;
            $variables = [];
            $this->visit($tree, $file, null, $variables);
        }
        foreach ($jsonFiles as $file => $source) {
            try {
                $this->manifest(json_decode($source, true, 512, JSON_THROW_ON_ERROR), $file);
            } catch (Throwable $error) {
                $this->errors[] = 'MANIFEST-PARSE '.$file.': '.$error->getMessage();
            }
        }

        if ($this->public === []) {
            $this->errors[] = 'SOURCE-EMPTY no public declarations under packages/*/src or products/*/src';
        }
        $roots = $this->ledger();
        foreach ($this->public as $symbol => $method) {
            if (! isset($this->usage[$symbol]) && ! isset($roots[$symbol])) {
                $this->errors[] = 'UNUSED-PUBLIC '.$symbol.' '.$method->source.':'.$method->node->getStartLine();
            }
        }
        ksort($this->public);
        sort($this->errors);
        $methods = [];
        foreach ($this->public as $symbol => $method) {
            $contracts = [];
            foreach ($this->relatives($method->class) as $parent) {
                $contract = $this->classes[$parent]['methods'][strtolower($method->node->name->toString())] ?? null;

                if ($parent !== $method->class && $contract !== null && $contract->export !== null) {
                    $contracts[] = $contract->symbol();
                }
            }
            $methods[] = ['symbol' => $symbol, 'source' => $method->source, 'source_sha256' => $method->hash, 'export' => $method->export, 'used_at' => $this->usage[$symbol] ?? [], 'contracts' => $contracts, 'parents' => $this->classes[$method->class]['parents']];
        }

        return ['methods' => $methods, 'errors' => $this->errors];
    }

    /** @return list<string> */
    private function files(): array
    {
        $files = [];
        $roots = ['tests', 'config', 'scripts'];
        foreach (['packages', 'products'] as $container) {
            $packages = glob($this->root.'/'.$container.'/*', GLOB_ONLYDIR);
            foreach ($packages === false ? [] : $packages as $package) {
                $relative = substr($package, strlen($this->root) + 1);
                foreach (['src', 'tests', 'config', 'routes', 'database', 'resources'] as $directory) {
                    $roots[] = $relative.'/'.$directory;
                }
                $files[] = $relative.'/composer.json';
            }
        }
        $files[] = 'composer.json';
        foreach ($roots as $directory) {
            if (! is_dir($this->root.'/'.$directory)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || ! in_array($file->getExtension(), ['php', 'json'], true)) {
                    continue;
                }
                $relative = substr($file->getPathname(), strlen($this->root) + 1);

                if (preg_match('~/(?:vendor|node_modules|build)/~', $relative) === 1 || $relative === 'scripts/quality/public-entrypoints.json') {
                    continue;
                }
                $files[] = $relative;
            }
        }

        return array_values(array_unique(array_filter($files, fn (string $file): bool => is_file($this->root.'/'.$file))));
    }

    /** @param list<Node> $nodes */
    private function declarations(array $nodes, string $file, string $hash): void
    {
        foreach ($nodes as $node) {
            if ($node instanceof Stmt\Function_ && isset($node->namespacedName)) {
                $this->functions[$node->namespacedName->toString()] = ['node' => $node, 'source' => $file];
            }

            if ($node instanceof Stmt\Use_ || $node instanceof Stmt\GroupUse) {
                foreach ($node->uses as $use) {
                    $name = ($node instanceof Stmt\GroupUse ? $node->prefix->toString().'\\' : '').$use->name->toString();
                    $this->imports[$file][$use->alias?->toString() ?? $use->name->getLast()] = $name;
                }
            }

            if ($node instanceof Stmt\ClassLike) {
                $class = $node->name !== null && isset($node->namespacedName) ? $node->namespacedName->toString() : 'Anonymous\\Source_'.substr(hash('sha256', $file), 0, 16).'_Line'.$node->getStartLine().'_Offset'.$node->getStartFilePos();
                $node->setAttribute('unusedPublicClass', $class);
                $this->classImports[$class] = $this->imports[$file] ?? [];
                $parents = [];

                if ($node instanceof Stmt\Class_ && $node->extends !== null) {
                    $parents[] = $node->extends->toString();
                }
                foreach ($node instanceof Stmt\Interface_ ? $node->extends : ($node instanceof Stmt\Class_ || $node instanceof Stmt\Enum_ ? $node->implements : []) as $parent) {
                    $parents[] = $parent->toString();
                }
                $export = null;
                foreach ($node->attrGroups as $group) {
                    foreach ($group->attrs as $attribute) {
                        $export = match ($attribute->name->toString()) {
                            Api::class => 'external-api',
                            Spi::class => 'external-spi',
                            default => $export,
                        };
                    }
                }
                $properties = [];
                $methods = [];
                foreach ($node->stmts as $member) {
                    if ($member instanceof Stmt\TraitUse) {
                        foreach ($member->traits as $trait) {
                            $parents[] = $trait->toString();
                        }
                    } elseif ($member instanceof Stmt\Property) {
                        foreach ($member->props as $property) {
                            $properties[$property->name->toString()] = $this->type($member->type, $class);
                            $this->propertyDocs[$class][$property->name->toString()] = ['doc' => $member->getDocComment()?->getText() ?? '', 'source' => $file, 'parameter' => null];
                        }
                    } elseif ($member instanceof Stmt\ClassConst) {
                        foreach ($member->consts as $constant) {
                            $this->constants[$class][$constant->name->toString()] = $constant->value;
                        }
                    } elseif ($member instanceof Stmt\ClassMethod) {
                        $method = new MethodDeclaration(class: $class, node: $member, source: $file, hash: $hash, export: $export);
                        $methods[strtolower($member->name->toString())] = $method;
                        foreach ($member->params as $param) {
                            if ($param->flags !== 0 && $param->var instanceof Expr\Variable && is_string($param->var->name)) {
                                $properties[$param->var->name] = $this->type($param->type, $class);
                                $this->propertyDocs[$class][$param->var->name] = ['doc' => $member->getDocComment()?->getText() ?? '', 'source' => $file, 'parameter' => $param->var->name];
                            }
                        }

                        if ($member->isPublic() && preg_match('~^(packages|products)/[^/]+/src/~', $file) === 1) {
                            if (isset($this->public[$method->symbol()])) {
                                $this->errors[] = 'SOURCE-DUPLICATE '.$method->symbol().' '.$file;
                            }
                            $this->public[$method->symbol()] = $method;
                        }
                    }
                }
                $this->classes[$class] = ['node' => $node, 'parents' => $parents, 'properties' => $properties, 'methods' => $methods];
            }
            $children = get_object_vars($node);
            foreach ($node->getSubNodeNames() as $field) {
                $value = $children[$field] ?? null;

                if ($value instanceof Node) {
                    $this->declarations([$value], $file, $hash);
                } elseif (is_array($value)) {
                    $this->declarations(array_values(array_filter($value, static fn (mixed $item): bool => $item instanceof Node)), $file, $hash);
                }
            }
        }
    }

    /** @return list<string> */
    private function type(?Node $type, ?string $class): array
    {
        if ($type instanceof Name) {
            return match (strtolower($type->toString())) {
                'self', 'static' => $class === null ? [] : [$class],
                'parent' => $class === null ? [] : ($this->classes[$class]['parents'] ?? []),
                default => [$type->toString()],
            };
        }

        if ($type instanceof Node\NullableType) {
            return $this->type($type->type, $class);
        }

        if ($type instanceof Node\UnionType || $type instanceof Node\IntersectionType) {
            $types = [];
            foreach ($type->types as $part) {
                $types = [...$types, ...$this->type($part, $class)];
            }

            return array_values(array_unique($types));
        }

        return [];
    }

    /** @param list<string> $seen
     * @return list<string>
     */
    private function relatives(string $class, array $seen = []): array
    {
        if (isset($this->ancestors[$class])) {
            return $this->ancestors[$class];
        }

        if (in_array($class, $seen, true)) {
            return [];
        }
        $relatives = [$class];
        foreach ($this->classes[$class]['parents'] ?? [] as $parent) {
            $relatives = [...$relatives, ...$this->relatives($parent, [...$seen, $class])];
        }

        return $this->ancestors[$class] = array_values(array_unique($relatives));
    }

    /** @param list<string> $classes */
    private function invoke(array $classes, string $name, string $file, int $line): void
    {
        foreach ($classes as $receiver) {
            // Typed interface/base calls dispatch to their known implementations.
            foreach ($this->dispatch[$receiver] ?? [$receiver] as $owner) {
                $method = $this->classes[$owner]['methods'][strtolower($name)] ?? null;

                if ($method !== null && isset($this->public[$method->symbol()])) {
                    $anchor = $file.':'.$line;
                    $this->usage[$method->symbol()] ??= [];

                    if (! in_array($anchor, $this->usage[$method->symbol()], true)) {
                        $this->usage[$method->symbol()][] = $anchor;
                    }
                }
            }
        }
    }

    /** @param array<string, list<string>> $variables
     * @return list<string>
     */
    private function expression(Node $node, ?string $class, array $variables): array
    {
        if ($node instanceof Expr\Variable && is_string($node->name)) {
            return $node->name === 'this' && $class !== null ? [$class] : ($variables[$node->name] ?? []);
        }

        if ($node instanceof Expr\New_) {
            if ($node->class instanceof Stmt\Class_) {
                $identity = $node->class->getAttribute('unusedPublicClass');

                return is_string($identity) ? [$identity] : [];
            }

            return $node->class instanceof Name ? $this->type($node->class, $class) : $this->expression($node->class, $class, $variables);
        }

        if ($node instanceof Expr\ClassConstFetch && $node->class instanceof Name && $node->name instanceof Node\Identifier) {
            if (strtolower($node->name->toString()) === 'class') {
                return $this->type($node->class, $class);
            }
            $types = [];
            foreach ($this->type($node->class, $class) as $owner) {
                $constant = $this->constants[$owner][$node->name->toString()] ?? null;

                if ($constant !== null) {
                    $types = [...$types, ...$this->expression($constant, $class, $variables)];
                }
            }

            return array_values(array_unique($types));
        }

        if ($node instanceof Expr\Array_) {
            $types = [];
            foreach ($node->items as $item) {
                $types = [...$types, ...$this->expression($item->value, $class, $variables)];
            }

            return array_values(array_unique($types));
        }

        if (($node instanceof Expr\PropertyFetch || $node instanceof Expr\NullsafePropertyFetch) && $node->name instanceof Node\Identifier) {
            $types = [];

            if ($node->var instanceof Expr\Variable && $node->var->name === 'this' && $class === null) {
                $types = $this->testProperties[$this->currentFile][$node->name->toString()] ?? [];

                if ($node->name->toString() === 'app' && str_contains($this->currentFile, '/tests/')) {
                    $types[] = \Illuminate\Contracts\Foundation\Application::class;
                }
            }
            foreach ($this->expression($node->var, $class, $variables) as $receiver) {
                foreach ($this->relatives($receiver) as $owner) {
                    $types = [...$types, ...($this->classes[$owner]['properties'][$node->name->toString()] ?? [])];

                    if ($node->name->toString() === 'app' && in_array($owner, [ServiceProvider::class, TestCase::class, \Illuminate\Foundation\Testing\TestCase::class], true)) {
                        $types[] = \Illuminate\Contracts\Foundation\Application::class;
                    }
                }
            }

            return array_values(array_unique($types));
        }

        if ($node instanceof Expr\FuncCall && $node->name instanceof Name && in_array(strtolower($node->name->toString()), ['app', 'resolve'], true)) {
            if (isset($node->args[0]) && $node->args[0] instanceof Node\Arg) {
                return $this->classArgument($node->args[0]->value, $class, $variables);
            }

            return strtolower($node->name->toString()) === 'app' ? [\Illuminate\Contracts\Foundation\Application::class] : [];
        }

        if ($node instanceof Expr\FuncCall && $node->name instanceof Name) {
            $function = $this->functions[$node->name->getAttribute('namespacedName') instanceof Name ? $node->name->getAttribute('namespacedName')->toString() : $node->name->toString()] ?? null;

            if ($function !== null) {
                $return = $this->type($function['node']->returnType, $class);

                return $return !== [] ? $return : $this->docTypes($function['node']->getDocComment()?->getText() ?? '', 'return', $function['source'], $class);
            }
        }

        if (($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\StaticCall) && $node->name instanceof Node\Identifier) {
            if (! $node instanceof Expr\StaticCall && in_array(strtolower($node->name->toString()), ['make', 'makewith', 'get', 'tagged'], true) && isset($node->args[0]) && $node->args[0] instanceof Node\Arg) {
                $container = $this->expression($node->var, $class, $variables);

                if (array_any($container, static fn (string $type): bool => in_array($type, ['Illuminate\\Contracts\\Container\\Container', 'Illuminate\\Container\\Container', \Illuminate\Contracts\Foundation\Application::class, Application::class, 'Psr\\Container\\ContainerInterface'], true))) {
                    return $this->classArgument($node->args[0]->value, $class, $variables);
                }
            }
            $receivers = $node instanceof Expr\StaticCall ? ($node->class instanceof Name ? $this->type($node->class, $class) : $this->expression($node->class, $class, $variables)) : $this->expression($node->var, $class, $variables);
            $types = [];
            foreach ($receivers as $receiver) {
                foreach ($this->relatives($receiver) as $owner) {
                    $method = $this->classes[$owner]['methods'][strtolower($node->name->toString())] ?? null;

                    if ($method !== null) {
                        $return = $this->type($method->node->returnType, $receiver);
                        $types = [...$types, ...($return !== [] ? $return : $this->docTypes($method->node->getDocComment()?->getText() ?? '', 'return', $method->source, $receiver))];
                    }
                }
            }

            return array_values(array_unique($types));
        }

        return [];
    }

    /** @param array<string, list<string>> $variables
     * @return list<string>
     */
    private function classArgument(Node $node, ?string $class, array $variables): array
    {
        if ($node instanceof Node\Scalar\String_) {
            return isset($this->classes[$node->value]) ? [$node->value] : [];
        }

        return $this->expression($node, $class, $variables);
    }

    /** @param list<Node> $nodes
     * @param  array<string, list<string>>  $variables
     */
    private function visit(array $nodes, string $file, ?string $class, array &$variables): void
    {
        foreach ($nodes as $node) {
            $comment = $node->getDocComment()?->getText() ?? '';

            if (preg_match_all('~@var\s+(\S+)\s+\$([A-Za-z_][A-Za-z0-9_]*)~', $comment, $annotations, PREG_SET_ORDER) !== false) {
                foreach ($annotations as $annotation) {
                    $variables[$annotation[2]] = $this->docTypes('@var '.$annotation[1], 'var', $file, $class);
                }
            }

            if ($node instanceof Stmt\ClassLike) {
                $identity = $node->getAttribute('unusedPublicClass');
                $nestedClass = is_string($identity) ? $identity : null;
                $nestedVariables = [];
                $this->visit(array_values($node->attrGroups), $file, $class, $variables);
                $this->visit(array_values($node->stmts), $file, $nestedClass, $nestedVariables);

                continue;
            }

            if ($node instanceof Stmt\ClassMethod || $node instanceof Stmt\Function_ || $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
                $nestedVariables = $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction ? $variables : [];
                foreach ($node->params as $param) {
                    if ($param->var instanceof Expr\Variable && is_string($param->var->name)) {
                        $native = $this->type($param->type, $class);
                        $nestedVariables[$param->var->name] = $native !== [] ? $native : $this->docTypes($comment, 'param', $file, $class, $param->var->name);
                    }
                }
                $this->visit($node instanceof Expr\ArrowFunction ? [$node->expr] : array_values($node->stmts ?? []), $file, $class, $nestedVariables);

                continue;
            }

            if ($node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
                $inferred = $this->expression($node->expr, $class, $variables);

                if ($inferred !== [] || ! isset($variables[$node->var->name])) {
                    $variables[$node->var->name] = $inferred;
                }
            }

            if ($node instanceof Expr\Assign && $node->var instanceof Expr\PropertyFetch && $node->var->name instanceof Node\Identifier && $node->var->var instanceof Expr\Variable && $node->var->var->name === 'this') {
                $inferred = $this->expression($node->expr, $class, $variables);

                if ($class === null) {
                    $this->testProperties[$file][$node->var->name->toString()] = $inferred;
                } elseif ($inferred !== []) {
                    $this->classes[$class]['properties'][$node->var->name->toString()] = $inferred;
                }
            }

            if ($node instanceof Stmt\Foreach_ && $node->valueVar instanceof Expr\Variable && is_string($node->valueVar->name)) {
                $variables[$node->valueVar->name] = $this->expression($node->expr, $class, $variables);
            }

            if ($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall || $node instanceof Expr\StaticCall) {
                if ($node->name instanceof Node\Identifier) {
                    $receivers = $node instanceof Expr\StaticCall ? ($node->class instanceof Name ? $this->type($node->class, $class) : $this->expression($node->class, $class, $variables)) : $this->expression($node->var, $class, $variables);
                    $this->invoke($receivers, $node->name->toString(), $file, $node->getStartLine());

                    if (array_any($receivers, static fn (string $type): bool => in_array($type, ['Illuminate\\Contracts\\Container\\Container', 'Illuminate\\Container\\Container', \Illuminate\Contracts\Foundation\Application::class, Application::class, 'Psr\\Container\\ContainerInterface'], true))) {
                        if (in_array(strtolower($node->name->toString()), ['make', 'makewith', 'get', 'tagged'], true)) {
                            $this->invoke($this->expression($node, $class, $variables), '__construct', $file, $node->getStartLine());
                        } elseif (in_array(strtolower($node->name->toString()), ['bind', 'singleton', 'scoped'], true)) {
                            $argument = $node->args[1] ?? $node->args[0] ?? null;

                            if ($argument instanceof Node\Arg) {
                                $this->invoke($this->classArgument($argument->value, $class, $variables), '__construct', $file, $node->getStartLine());
                            }
                        }
                    }
                }
            } elseif ($node instanceof Expr\New_) {
                $this->invoke($this->expression($node, $class, $variables), '__construct', $file, $node->getStartLine());
            } elseif ($node instanceof Expr\FuncCall && $node->name instanceof Name && in_array(strtolower($node->name->toString()), ['app', 'resolve'], true) && isset($node->args[0]) && $node->args[0] instanceof Node\Arg) {
                $this->invoke($this->classArgument($node->args[0]->value, $class, $variables), '__construct', $file, $node->getStartLine());
            } elseif ($node instanceof Expr\FuncCall && $node->name instanceof Expr) {
                $this->invoke($this->expression($node->name, $class, $variables), '__invoke', $file, $node->getStartLine());
            } elseif ($node instanceof Expr\FuncCall && in_array(strtolower($node->name->toString()), ['call_user_func', 'call_user_func_array', 'forward_static_call', 'forward_static_call_array'], true) && isset($node->args[0]) && $node->args[0] instanceof Node\Arg && $node->args[0]->value instanceof Node\Scalar\String_) {
                $this->callbackString($node->args[0]->value->value, $file, $node->getStartLine());
            } elseif ($node instanceof Node\ArrayItem && $node->key instanceof Node\Scalar\String_ && in_array($node->key->value, ['handler', 'callable', 'entrypoint', 'callback', 'listener'], true) && $node->value instanceof Node\Scalar\String_) {
                $this->callbackString($node->value->value, $file, $node->getStartLine());
            } elseif ($node instanceof Node\Attribute) {
                $this->invoke([$node->name->toString()], '__construct', $file, $node->getStartLine());
            } elseif ($node instanceof Expr\Array_ && count($node->items) === 2 && $node->items[0]->key === null && $node->items[1]->key === null && $node->items[1]->value instanceof Node\Scalar\String_) {
                $this->invoke($this->classArgument($node->items[0]->value, $class, $variables), $node->items[1]->value->value, $file, $node->getStartLine());

            }
            $children = get_object_vars($node);
            foreach ($node->getSubNodeNames() as $field) {
                $value = $children[$field] ?? null;

                if ($value instanceof Node) {
                    $this->visit([$value], $file, $class, $variables);
                } elseif (is_array($value)) {
                    $this->visit(array_values(array_filter($value, static fn (mixed $item): bool => $item instanceof Node)), $file, $class, $variables);
                }
            }
        }
    }

    /** @return list<string> */
    private function docTypes(string $comment, string $tag, string $file, ?string $class, ?string $parameter = null): array
    {
        $pattern = '~@'.preg_quote($tag, '~').'\s+(\S+)'.($parameter !== null ? '\s+\$'.preg_quote($parameter, '~').'\b' : '').'~';

        if (preg_match($pattern, $comment, $match) !== 1) {
            return [];
        }
        preg_match_all('~[A-Za-z_\\\\][A-Za-z0-9_\\\\]*~', $match[1], $tokens);
        $types = [];
        foreach ($tokens[0] as $token) {
            $name = ltrim($token, '\\');
            $namespace = $class !== null && str_contains($class, '\\') ? substr($class, 0, (int) strrpos($class, '\\')).'\\' : '';
            $parts = explode('\\', $name, 2);
            $imports = $class === null ? ($this->imports[$file] ?? []) : ($this->classImports[$class] ?? []);
            $resolved = isset($imports[$parts[0]]) ? $imports[$parts[0]].(isset($parts[1]) ? '\\'.$parts[1] : '') : $namespace.$name;

            if (in_array(strtolower($name), ['self', 'static'], true) && $class !== null) {
                $types[] = $class;
            } elseif (isset($this->classes[$name])) {
                $types[] = $name;
            } elseif (isset($this->classes[$resolved])) {
                $types[] = $resolved;
            }
        }

        return array_values(array_unique($types));
    }

    private function callbackString(string $value, string $file, int $line): void
    {
        if (preg_match('~^([A-Za-z_][A-Za-z0-9_\\\\]*)::([A-Za-z_][A-Za-z0-9_]*)$~D', $value, $match) === 1) {
            $this->invoke([$match[1]], $match[2], $file, $line);
        }
    }

    private function manifest(mixed $value, string $file, ?string $key = null): void
    {
        if (is_string($value) && in_array($key, ['handler', 'callable', 'entrypoint', 'callback', 'listener'], true)) {
            $this->callbackString($value, $file, 1);
        } elseif (is_string($value) && $key === 'providers' && in_array(ServiceProvider::class, $this->relatives($value), true)) {
            foreach (['__construct', 'register', 'boot'] as $method) {
                $this->invoke([$value], $method, $file, 1);
            }
        } elseif (is_array($value)) {
            foreach ($value as $childKey => $child) {
                $this->manifest($child, $file, is_string($childKey) ? $childKey : $key);
            }
        }
    }

    /** @return array<string, true> */
    private function ledger(): array
    {
        $roots = [];

        try {
            $path = $this->root.'/scripts/quality/public-entrypoints.json';
            $bytes = is_file($path) ? file_get_contents($path) : false;

            if ($bytes === false) {
                throw new RuntimeException('missing scripts/quality/public-entrypoints.json');
            }
            $ledger = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($ledger) || array_keys($ledger) !== ['schema', 'entries'] || $ledger['schema'] !== 'unused-public-entrypoints/1' || ! is_array($ledger['entries']) || ! array_is_list($ledger['entries'])) {
                throw new RuntimeException('expected exact unused-public-entrypoints/1 schema and entries list');
            }
            foreach ($ledger['entries'] as $entry) {
                if (! is_array($entry) || array_diff(array_keys($entry), ['symbol', 'source', 'source_sha256', 'kind', 'reason', 'root', 'expires']) !== [] || count($entry) !== 7) {
                    $this->errors[] = 'ENTRYPOINT-CONFIG malformed ledger row';

                    continue;
                }
                $symbol = $entry['symbol'];

                if (! is_string($symbol) || preg_match('~^[A-Za-z_][A-Za-z0-9_\\\\]*::[A-Za-z_][A-Za-z0-9_]*$~D', $symbol) !== 1 || ! isset($this->public[$symbol]) || isset($roots[$symbol])) {
                    $this->errors[] = 'ENTRYPOINT-CONFIG unknown, duplicate or wildcard symbol '.(is_string($symbol) ? $symbol : '<invalid>');

                    continue;
                }
                $method = $this->public[$symbol];
                $proof = $entry['root'];
                $valid = $entry['source'] === $method->source && $entry['source_sha256'] === $method->hash && is_string($entry['reason']) && strlen(trim($entry['reason'])) >= 20 && in_array($entry['kind'], ['external-api', 'external-spi', 'external-implementation', 'framework', 'serializer', 'dynamic'], true);

                if (in_array($entry['kind'], ['external-api', 'external-spi'], true)) {
                    $valid = $valid && $method->export === $entry['kind'];
                }

                if ($entry['expires'] !== null) {
                    $expires = is_string($entry['expires']) ? DateTimeImmutable::createFromFormat('!Y-m-d', $entry['expires']) : false;
                    $valid = $valid && $expires !== false && $expires->format('Y-m-d') === $entry['expires'] && $entry['expires'] >= gmdate('Y-m-d');
                }

                if (! is_array($proof) || array_keys($proof) !== ['path', 'anchor', 'sha256'] || ! is_string($proof['path']) || ! is_string($proof['anchor']) || ! is_string($proof['sha256']) || $proof['anchor'] === '' || preg_match('~^[a-f0-9]{64}$~D', $proof['sha256']) !== 1 || str_contains($proof['path'], '..') || str_starts_with($proof['path'], '/') || str_contains($proof['path'], '*') || $proof['path'] === 'scripts/quality/public-entrypoints.json') {
                    $valid = false;
                } else {
                    $evidence = is_file($this->root.'/'.$proof['path']) ? file_get_contents($this->root.'/'.$proof['path']) : false;
                    $valid = $valid && $evidence !== false && hash('sha256', $evidence) === $proof['sha256'] && str_contains($evidence, $proof['anchor']);

                    if (in_array($entry['kind'], ['external-api', 'external-spi'], true)) {
                        $valid = $valid && str_ends_with($proof['path'], '.md') && str_contains($proof['anchor'], $method->class);
                    } elseif ($entry['kind'] === 'external-implementation') {
                        $contracts = [];
                        foreach ($this->relatives($method->class) as $parent) {
                            $contract = $this->classes[$parent]['methods'][strtolower($method->node->name->toString())] ?? null;

                            if ($parent !== $method->class && $contract !== null && $contract->export !== null) {
                                $contracts[] = $parent;
                            }
                        }
                        $valid = $valid && str_ends_with($proof['path'], '.md') && array_any($contracts, static fn (string $contract): bool => str_contains($proof['anchor'], $contract));
                    }
                }

                if (! $valid) {
                    $this->errors[] = 'ENTRYPOINT-STALE '.$symbol.' invalid source hash, intended contract, root evidence or expiry';

                    continue;
                }
                $roots[$symbol] = true;
            }
        } catch (Throwable $error) {
            $this->errors[] = 'ENTRYPOINT-CONFIG '.$error->getMessage();
        }

        return $roots;
    }
}

try {
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (in_array($argument, ['--check', '--report'], true) && ! isset($options[$argument])) {
            $options[$argument] = true;
        } elseif (str_starts_with($argument, '--root=') && ! isset($options['root'])) {
            $options['root'] = substr($argument, 7);
        } else {
            throw new RuntimeException('unknown or duplicate argument '.$argument);
        }
    }
    $root = realpath($options['root'] ?? dirname(__DIR__, 2));

    if ($root === false) {
        throw new RuntimeException('root does not exist');
    }
    $report = new UsageScanner(root: $root)->scan();

    if (isset($options['--report'])) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    } else {
        echo 'unused-public: scanned '.count($report['methods']).' public methods; '.count($report['errors'])." failures\n";
    }
    foreach ($report['errors'] as $error) {
        fwrite(STDERR, $error."\n");
    }
    exit($report['errors'] === [] ? 0 : 1);
} catch (Throwable $error) {
    fwrite(STDERR, 'UNUSED-PUBLIC-CONFIG '.$error->getMessage()."\n");
    exit(2);
}
