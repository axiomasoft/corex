#!/usr/bin/env php
<?php

declare(strict_types=1);

namespace CoreX\Tooling\Quality;

use FilesystemIterator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use Packman\Attributes\Spi;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

/**
 * @phpstan-type Violation array{rule: string, file: string, line: int, symbol: string, message: string}
 * @phpstan-type Insertion array{file: string, offset: int, text: string, symbol: string, line: int}
 * @phpstan-type Report array{files: int, violations: list<Violation>, named_insertions: list<Insertion>, file_hashes: array<string, string>, abstract_non_spi: list<string>}
 */
final class SourceQuality
{
    /** @var array<string, Stmt\ClassLike> */
    private array $classes = [];

    /** @var array<string, Stmt\Function_> */
    private array $functions = [];

    /** @var array<string, array<Node>> */
    private array $trees = [];

    /** @var array<string, string> */
    private array $hashes = [];

    /** @var list<Violation> */
    private array $violations = [];

    /** @var list<Insertion> */
    private array $insertions = [];

    /** @var list<string> */
    private array $abstractNonSpi = [];

    /** @return Report */
    public function inspect(string $root): array
    {
        $parser = (new ParserFactory)->createForVersion(PhpVersion::fromString('8.4'));
        $finder = new NodeFinder;
        $roots = array_merge($this->roots($root.'/packages'), $this->roots($root.'/products'));

        if ($roots === []) {
            throw new RuntimeException('No maintained production source roots');
        }
        foreach ($roots as $sourceRoot) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $path = $file->getPathname();
                $relative = substr($path, strlen($root) + 1);
                $source = file_get_contents($path);

                if ($source === false) {
                    throw new RuntimeException('Unreadable source: '.$relative);
                }

                try {
                    $tree = $parser->parse($source) ?? [];
                    $tree = new NodeTraverser(new NameResolver)->traverse($tree);
                } catch (Throwable $error) {
                    throw new RuntimeException('Cannot parse '.$relative.': '.$error->getMessage(), previous: $error);
                }
                $this->trees[$relative] = $tree;
                $this->hashes[$relative] = hash('sha256', $source);
                foreach ($finder->findInstanceOf($tree, Stmt\ClassLike::class) as $class) {
                    $name = $class->namespacedName?->toString();

                    if ($name === null) {
                        continue;
                    }
                    $key = strtolower($name);

                    if (isset($this->classes[$key])) {
                        throw new RuntimeException('Duplicate source class: '.$name);
                    }
                    $this->classes[$key] = $class;
                    $this->classRules($class, $relative, $name);
                }
                foreach ($finder->findInstanceOf($tree, Stmt\Function_::class) as $function) {
                    $name = strtolower($function->namespacedName?->toString() ?? $function->name->toString());

                    if (isset($this->functions[$name])) {
                        throw new RuntimeException('Duplicate source function: '.$name);
                    }
                    $this->functions[$name] = $function;
                }
            }
        }

        if ($this->trees === []) {
            throw new RuntimeException('No maintained production PHP files');
        }
        foreach ($this->trees as $file => $tree) {
            $variables = [];
            $this->walk($tree, $file, null, $variables);
        }
        usort($this->violations, static fn (array $a, array $b): int => [$a['file'], $a['line'], $a['rule']] <=> [$b['file'], $b['line'], $b['rule']]);
        sort($this->abstractNonSpi);

        return ['files' => count($this->trees), 'violations' => $this->violations, 'named_insertions' => $this->insertions, 'file_hashes' => $this->hashes, 'abstract_non_spi' => $this->abstractNonSpi];
    }

    /** @return list<string> */
    private function roots(string $base): array
    {
        return ($roots = glob($base.'/*/src', GLOB_ONLYDIR)) === false ? [] : $roots;
    }

    private function spi(Stmt\ClassLike $class): bool
    {
        foreach ($class->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if ($attribute->name->toString() === Spi::class) {
                    return true;
                }
            }
        }

        return false;
    }

    private function classRules(Stmt\ClassLike $class, string $file, string $name): void
    {
        if (! $class instanceof Stmt\Class_) {
            return;
        }

        if ($class->isAbstract()) {
            if (! $this->spi($class)) {
                $this->abstractNonSpi[] = $file.':'.$class->getStartLine().': '.$name;
            }

            return;
        }

        if (! $class->isFinal() && ! $this->spi($class)) {
            $this->violation('final', $file, $class, $name, 'concrete production class must be final or an existing #[Spi] extension class');
        }
    }

    private function valueDto(Stmt\Class_ $class): bool
    {
        $name = $class->namespacedName?->toString() ?? '';

        // Data is also a feature namespace; only its value subdirectories are DTOs.
        if (preg_match('/\\\\(?:Data|DTO|Dtos)\\\\(?!(?:Internal|Laravel|Contracts|Exceptions|Enums|Testing|PHPStan)\\\\)/', $name) !== 1) {
            return false;
        }

        if ($this->inherits($name, Model::class) || $this->inherits($name, ServiceProvider::class)) {
            return false;
        }
        $state = $class->getProperties() !== [];
        foreach ($class->getMethods() as $method) {
            foreach ($method->params as $parameter) {
                $state = $state || $parameter->isPromoted();
            }

            // Existing SPI fluent declarations carry intentionally mutable builder state.
            if ($this->spi($class) && $method->name->toLowerString() !== '__construct') {
                $writes = (new NodeFinder)->find($method->stmts ?? [], static function (Node $node): bool {
                    $target = $node instanceof Expr\Assign || $node instanceof Expr\AssignOp
                        || $node instanceof Expr\PreInc || $node instanceof Expr\PostInc
                        || $node instanceof Expr\PreDec || $node instanceof Expr\PostDec ? $node->var : null;
                    while ($target instanceof Expr\ArrayDimFetch) {
                        $target = $target->var;
                    }

                    return $target instanceof Expr\PropertyFetch && $target->var instanceof Expr\Variable && $target->var->name === 'this';
                });

                if ($writes !== []) {
                    return false;
                }
            }
        }

        return $state;
    }

    /** @param list<string> $seen */
    private function inherits(string $name, string $parent, array $seen = []): bool
    {
        if (strtolower($name) === strtolower($parent)) {
            return true;
        }
        $key = strtolower($name);
        $class = $this->classes[$key] ?? null;

        if (in_array($key, $seen, true) || ! $class instanceof Stmt\Class_ || $class->extends === null) {
            return false;
        }

        return $this->inherits($class->extends->toString(), $parent, [...$seen, $key]);
    }

    /**
     * @param  Node|array<Node>|null  $nodes
     * @param  array<string, string>  $variables
     */
    private function walk(Node|array|null $nodes, string $file, ?string $scope, array &$variables): void
    {
        if (is_array($nodes)) {
            foreach ($nodes as $node) {
                $this->walk($node, $file, $scope, $variables);
            }

            return;
        }

        if ($nodes === null) {
            return;
        }
        $node = $nodes;

        if ($node instanceof Stmt\ClassLike) {
            $className = $node->namespacedName?->toString();

            if ($node instanceof Stmt\Class_ && $className !== null && $node->isReadonly()
                && ($this->inherits($className, Model::class) || $this->inherits($className, ServiceProvider::class))) {
                $this->violation('readonly-framework', $file, $node, $className, 'ORM models and framework providers require mutable state');
            }

            if ($node instanceof Stmt\Class_ && $className !== null && ! $node->isAbstract() && $this->valueDto($node) && ! $node->isReadonly()) {
                $this->violation('readonly', $file, $node, $className, 'immutable value DTO must use a readonly class');
            }
            $local = [];
            $this->walk($node->stmts, $file, $className, $local);

            return;
        }

        if ($node instanceof Stmt\ClassMethod || $node instanceof Stmt\Function_ || $node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            $local = $node instanceof Expr\ArrowFunction ? $variables : [];

            if ($node instanceof Expr\Closure) {
                foreach ($node->uses as $use) {
                    if ($use->byRef && is_string($use->var->name)) {
                        unset($variables[$use->var->name]);
                    }

                    if (! $use->byRef && is_string($use->var->name) && isset($variables[$use->var->name])) {
                        $local[$use->var->name] = $variables[$use->var->name];
                    }
                }
            }
            foreach ($node->params as $parameter) {
                $type = $this->typeName($parameter->type, $scope);

                if ($type !== null && $parameter->var instanceof Expr\Variable && is_string($parameter->var->name)) {
                    $local[$parameter->var->name] = $type;
                }
            }
            $this->walk($node instanceof Expr\ArrowFunction ? $node->expr : $node->stmts, $file, $scope, $local);

            return;
        }

        if ($node instanceof Stmt\If_) {
            $this->walk($node->cond, $file, $scope, $variables);
            $branches = [];
            $local = $variables;
            $this->walk($node->stmts, $file, $scope, $local);
            $branches[] = $local;
            foreach ($node->elseifs as $elseif) {
                $local = $variables;
                $this->walk($elseif->cond, $file, $scope, $local);
                $this->walk($elseif->stmts, $file, $scope, $local);
                $branches[] = $local;
            }
            $local = $variables;
            $this->walk($node->else?->stmts, $file, $scope, $local);
            $branches[] = $local;
            $variables = $this->commonVariables($branches);

            return;
        }

        if ($node instanceof Stmt\Foreach_ || $node instanceof Stmt\For_ || $node instanceof Stmt\While_ || $node instanceof Stmt\Do_) {
            // A later iteration can reach a call before an assignment in this iteration.
            $this->forgetWrites($node, $variables);
            $local = $variables;
            foreach ($node->getSubNodeNames() as $property) {
                $child = get_object_vars($node)[$property] ?? null;

                if ($child instanceof Node || is_array($child)) {
                    $this->walk($child instanceof Node ? $child : array_values(array_filter($child, static fn (mixed $value): bool => $value instanceof Node)), $file, $scope, $local);
                }
            }
            $variables = $this->commonVariables([$variables, $local]);

            return;
        }

        if ($node instanceof Stmt\Switch_ || $node instanceof Stmt\TryCatch) {
            $this->forgetWrites($node, $variables);
            $branches = [$variables];

            if ($node instanceof Stmt\Switch_) {
                $this->walk($node->cond, $file, $scope, $variables);
                foreach ($node->cases as $case) {
                    $local = $variables;
                    $this->walk($case->cond, $file, $scope, $local);
                    $this->walk($case->stmts, $file, $scope, $local);
                    $branches[] = $local;
                }
            } else {
                $local = $variables;
                $this->walk($node->stmts, $file, $scope, $local);
                $branches[] = $local;
                foreach ($node->catches as $catch) {
                    $local = $variables;

                    if ($catch->var !== null && is_string($catch->var->name)) {
                        unset($local[$catch->var->name]);
                    }
                    $this->walk($catch->stmts, $file, $scope, $local);
                    $branches[] = $local;
                }
            }
            $variables = $this->commonVariables($branches);

            if ($node instanceof Stmt\TryCatch) {
                $this->walk($node->finally?->stmts, $file, $scope, $variables);
            }

            return;
        }

        if ($node instanceof Expr\Ternary || $node instanceof Expr\Match_) {
            $this->forgetWrites($node, $variables);
            $branches = [$variables];
            $this->walk($node->cond, $file, $scope, $variables);
            $expressions = $node instanceof Expr\Ternary ? [$node->if, $node->else] : $node->arms;
            foreach ($expressions as $expression) {
                $local = $variables;
                $this->walk($expression, $file, $scope, $local);
                $branches[] = $local;
            }
            $variables = $this->commonVariables($branches);

            return;
        }

        if ($node instanceof Expr\BinaryOp\BooleanAnd || $node instanceof Expr\BinaryOp\LogicalAnd
            || $node instanceof Expr\BinaryOp\BooleanOr || $node instanceof Expr\BinaryOp\LogicalOr
            || $node instanceof Expr\BinaryOp\Coalesce) {
            $this->walk($node->left, $file, $scope, $variables);
            $local = $variables;
            $this->walk($node->right, $file, $scope, $local);
            $variables = $this->commonVariables([$variables, $local]);

            return;
        }

        if ($node instanceof Expr\Assign) {
            $this->walk($node->expr, $file, $scope, $variables);

            if ($node->var instanceof Expr\Variable && is_string($node->var->name)) {
                $type = $this->expressionType($node->expr, $scope, $variables);
                unset($variables[$node->var->name]);

                if ($type !== null) {
                    $variables[$node->var->name] = $type;
                }
            }

            return;
        }

        if ($node instanceof Expr\AssignRef || $node instanceof Expr\AssignOp || $node instanceof Stmt\Unset_) {
            $this->forgetWrites($node, $variables);
        }

        if ($node instanceof Expr\CallLike && ! $node->isFirstClassCallable()) {
            $this->callRule($node, $file, $scope, $variables);
        }
        foreach ($node->getSubNodeNames() as $property) {
            $child = get_object_vars($node)[$property] ?? null;

            if ($child instanceof Node || is_array($child)) {
                $children = $child instanceof Node ? $child : array_values(array_filter($child, static fn (mixed $value): bool => $value instanceof Node));
                $this->walk($children, $file, $scope, $variables);
            }
        }
    }

    /** @param array<string, string> $variables */
    private function forgetWrites(Node $node, array &$variables): void
    {
        foreach ((new NodeFinder)->find([$node], static fn (Node $child): bool => $child instanceof Expr\Assign
            || $child instanceof Expr\AssignRef || $child instanceof Expr\AssignOp
            || $child instanceof Stmt\Foreach_ || $child instanceof Stmt\Unset_) as $write) {
            if ($write instanceof Stmt\Foreach_) {
                $targets = [$write->keyVar, $write->valueVar];
            } elseif ($write instanceof Stmt\Unset_) {
                $targets = $write->vars;
            } elseif ($write instanceof Expr\Assign || $write instanceof Expr\AssignRef || $write instanceof Expr\AssignOp) {
                $targets = [$write->var];
            } else {
                $targets = [];
            }
            foreach ($targets as $target) {
                if ($target instanceof Expr\Variable && is_string($target->name)) {
                    unset($variables[$target->name]);
                }
            }
        }
    }

    /**
     * @param  list<array<string, string>>  $branches
     * @return array<string, string>
     */
    private function commonVariables(array $branches): array
    {
        $common = $branches[0] ?? [];
        foreach ($branches as $branch) {
            foreach ($common as $name => $type) {
                if (($branch[$name] ?? null) !== $type) {
                    unset($common[$name]);
                }
            }
        }

        return $common;
    }

    private function typeName(?Node $type, ?string $scope): ?string
    {
        if ($type instanceof Node\NullableType) {
            return $this->typeName($type->type, $scope);
        }

        if ($type instanceof Name) {
            $name = $type->toString();

            if (strtolower($name) === 'self') {
                return $scope;
            }

            if (strtolower($name) === 'parent') {
                $class = $scope === null ? null : ($this->classes[strtolower($scope)] ?? null);

                return $class instanceof Stmt\Class_ ? $class->extends?->toString() : null;
            }

            return strtolower($name) === 'static' ? null : $name;
        }

        return null;
    }

    /** @param array<string, string> $variables */
    private function expressionType(Expr $expression, ?string $scope, array $variables): ?string
    {
        if ($expression instanceof Expr\Variable && is_string($expression->name)) {
            return $expression->name === 'this' ? $scope : ($variables[$expression->name] ?? null);
        }

        if ($expression instanceof Expr\New_ && $expression->class instanceof Name) {
            return $this->typeName($expression->class, $scope);
        }

        if ($expression instanceof Expr\PropertyFetch || $expression instanceof Expr\NullsafePropertyFetch) {
            $owner = $this->expressionType($expression->var, $scope, $variables);

            if ($owner !== null && $expression->name instanceof Identifier) {
                return $this->propertyType($owner, $expression->name->toString());
            }
        }

        if ($expression instanceof Expr\CallLike && ! $expression->isFirstClassCallable()) {
            $signature = $this->signature($expression, $scope, $variables);

            if ($signature !== null) {
                return $this->typeName($signature['node']->returnType, $signature['owner']);
            }
        }

        return null;
    }

    /** @param list<string> $seen */
    private function propertyType(string $owner, string $property, array $seen = []): ?string
    {
        $key = strtolower($owner);
        $class = $this->classes[$key] ?? null;

        if ($class === null || in_array($key, $seen, true)) {
            return null;
        }
        foreach ($class->getProperties() as $declaration) {
            foreach ($declaration->props as $item) {
                if ($item->name->toString() === $property) {
                    return $this->typeName($declaration->type, $owner);
                }
            }
        }
        foreach ($class->getMethods() as $method) {
            if ($method->name->toLowerString() === '__construct') {
                foreach ($method->params as $parameter) {
                    if ($parameter->isPromoted() && $parameter->var instanceof Expr\Variable && $parameter->var->name === $property) {
                        return $this->typeName($parameter->type, $owner);
                    }
                }
            }
        }

        return $class instanceof Stmt\Class_ && $class->extends !== null
            ? $this->propertyType($class->extends->toString(), $property, [...$seen, $key]) : null;
    }

    /**
     * @param  list<string>  $seen
     * @return array{node: Stmt\ClassMethod, owner: string, symbol: string}|null
     */
    private function method(string $owner, string $method, array $seen = []): ?array
    {
        $key = strtolower($owner);
        $class = $this->classes[$key] ?? null;

        if ($class === null || in_array($key, $seen, true)) {
            return null;
        }
        foreach ($class->getMethods() as $declaration) {
            if ($declaration->name->toLowerString() === strtolower($method)) {
                return ['node' => $declaration, 'owner' => $owner, 'symbol' => $owner.'::'.$declaration->name->toString()];
            }
        }
        $parents = $class instanceof Stmt\Class_ && $class->extends !== null ? [$class->extends] : [];

        if ($class instanceof Stmt\Interface_) {
            $parents = $class->extends;
        }
        foreach ($class->stmts as $statement) {
            if ($statement instanceof Stmt\TraitUse) {
                // Adapted methods need their alias/precedence to be resolved explicitly.
                if ($statement->adaptations !== []) {
                    return null;
                }
                $parents = [...$statement->traits, ...$parents];
            }
        }
        foreach ($parents as $parent) {
            $signature = $this->method($parent->toString(), $method, [...$seen, $key]);

            if ($signature !== null) {
                return $signature;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $variables
     * @return array{node: Stmt\ClassMethod|Stmt\Function_, owner: ?string, symbol: string}|null
     */
    private function signature(Expr\CallLike $call, ?string $scope, array $variables): ?array
    {
        if ($call instanceof Expr\FuncCall && $call->name instanceof Name) {
            $resolved = $call->name->getAttribute('namespacedName');
            $name = $resolved instanceof Name ? $resolved->toString() : $call->name->toString();
            $function = $this->functions[strtolower($name)] ?? null;

            return $function === null ? null : ['node' => $function, 'owner' => null, 'symbol' => $name];
        }

        if ($call instanceof Expr\New_ && $call->class instanceof Name) {
            $owner = $this->typeName($call->class, $scope);
            $method = '__construct';
        } elseif ($call instanceof Expr\StaticCall && $call->class instanceof Name && $call->name instanceof Identifier) {
            $owner = $this->typeName($call->class, $scope);
            $method = $call->name->toString();
        } elseif (($call instanceof Expr\MethodCall || $call instanceof Expr\NullsafeMethodCall) && $call->name instanceof Identifier) {
            $owner = $this->expressionType($call->var, $scope, $variables);
            $method = $call->name->toString();
        } else {
            return null;
        }

        return $owner === null ? null : $this->method($owner, $method);
    }

    /** @param list<string> $seen */
    private function usesDeclaration(string $name, string $owner, array $seen = []): bool
    {
        $key = strtolower($name);

        if ($key === strtolower($owner)) {
            return true;
        }
        $class = $this->classes[$key] ?? null;

        if ($class === null || in_array($key, $seen, true)) {
            return false;
        }
        $parents = $class instanceof Stmt\Class_ && $class->extends !== null ? [$class->extends] : [];

        if ($class instanceof Stmt\Interface_) {
            $parents = $class->extends;
        }

        if ($class instanceof Stmt\Class_ || $class instanceof Stmt\Enum_) {
            $parents = [...$parents, ...$class->implements];
        }
        foreach ($class->stmts as $statement) {
            if ($statement instanceof Stmt\TraitUse) {
                $parents = [...$parents, ...$statement->traits];
            }
        }

        return array_any($parents, fn (Name $parent): bool => $this->usesDeclaration($parent->toString(), $owner, [...$seen, $key]));
    }

    /**
     * @param  array{node: Stmt\ClassMethod|Stmt\Function_, owner: ?string, symbol: string}  $signature
     * @return list<string>
     */
    private function conflictingParameters(array $signature): array
    {
        $method = $signature['node'];

        if (! $method instanceof Stmt\ClassMethod || $signature['owner'] === null || $method->isPrivate() || $method->name->toLowerString() === '__construct') {
            return [];
        }
        $expected = array_map(static fn (Node\Param $param): mixed => $param->var instanceof Expr\Variable ? $param->var->name : null, $method->params);
        $conflicts = [];
        foreach ($this->classes as $class) {
            $name = $class->namespacedName?->toString();

            if ($name === null || ! $this->usesDeclaration($name, $signature['owner'])) {
                continue;
            }
            $implementation = $this->method($name, $method->name->toString());

            if ($implementation === null || $implementation['node'] === $method) {
                continue;
            }
            $actual = array_map(static fn (Node\Param $param): mixed => $param->var instanceof Expr\Variable ? $param->var->name : null, $implementation['node']->params);

            // Optional extra parameters do not change the inherited parameter names.
            if (array_slice($actual, 0, count($expected)) !== $expected) {
                $names = array_map(static fn (mixed $name): string => is_string($name) ? $name : '?', $actual);
                $conflicts[] = $implementation['symbol'].'('.implode(', ', $names).')';
            }
        }

        return array_values(array_unique($conflicts));
    }

    /** @param array<string, string> $variables */
    private function callRule(Expr\CallLike $call, string $file, ?string $scope, array $variables): void
    {
        $arguments = $call->getArgs();

        if (count($arguments) < 2 || array_any($arguments, static fn (Arg $argument): bool => $argument->unpack)) {
            return;
        }
        $signature = $this->signature($call, $scope, $variables);

        if ($signature === null || ! str_starts_with($signature['symbol'], 'CoreX\\')
            || array_any($signature['node']->params, static fn (Node\Param $parameter): bool => $parameter->variadic)) {
            return;
        }
        $conflicts = $this->conflictingParameters($signature);

        if ($conflicts !== []) {
            $this->violation('signature', $file, $call, $signature['symbol'], 'inconsistent controlled override parameter names: '.implode('; ', $conflicts));

            return;
        }
        $params = $signature['node']->params;
        $names = [];
        foreach ($arguments as $position => $argument) {
            if ($argument->name !== null) {
                continue;
            }
            $parameter = $params[$position] ?? null;

            if ($parameter === null || ! $parameter->var instanceof Expr\Variable || ! is_string($parameter->var->name)) {
                throw new RuntimeException($file.':'.$call->getStartLine().': cannot map arguments to '.$signature['symbol']);
            }
            $names[] = $parameter->var->name;
            $this->insertions[] = ['file' => $file, 'offset' => $argument->getStartFilePos(), 'text' => $parameter->var->name.': ', 'symbol' => $signature['symbol'], 'line' => $argument->getStartLine()];
        }

        if ($names !== []) {
            $this->violation('named', $file, $call, $signature['symbol'], 'controlled multiargument call requires names: '.implode(', ', $names));
        }
    }

    private function violation(string $rule, string $file, Node $node, string $symbol, string $message): void
    {
        $this->violations[] = ['rule' => $rule, 'file' => $file, 'line' => $node->getStartLine(), 'symbol' => $symbol, 'message' => $message];
    }
}

/** @return list<string> */
function sourceQuality(string $root): array
{
    $report = (new SourceQuality)->inspect($root);

    return array_map(static fn (array $violation): string => $violation['file'].':'.$violation['line'].': '.$violation['rule'].': '.$violation['symbol'].': '.$violation['message'], $report['violations']);
}

if (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1) === []) {
    $options = getopt('', ['root:', 'json']);
    $root = $options['root'] ?? dirname(__DIR__, 2);

    try {
        if (! is_string($root)) {
            throw new RuntimeException('Usage: source-quality.php [--root=<fixture>] [--json]');
        }
        $report = (new SourceQuality)->inspect(rtrim($root, '/'));

        if (array_key_exists('json', $options)) {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
        } else {
            foreach ($report['violations'] as $violation) {
                fwrite(STDERR, $violation['file'].':'.$violation['line'].': '.$violation['rule'].': '.$violation['symbol'].': '.$violation['message']."\n");
            }
            echo 'source quality '.($report['violations'] === [] ? 'OK' : 'FAIL').' ('.$report['files']." files)\n";
        }
        exit($report['violations'] === [] ? 0 : 1);
    } catch (Throwable $error) {
        fwrite(STDERR, 'source quality FAIL: '.$error->getMessage()."\n");
        exit(1);
    }
}
