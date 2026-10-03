#!/usr/bin/env php
<?php

declare(strict_types=1);

/** DATA-003: check source-level query-builder and Eloquent writes against the table catalog. */

use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

$root = dirname(__DIR__);
require $root.'/vendor/autoload.php';

$catalog = [];
$externalModelTables = [];
foreach (file($root.'/docs/architecture/table-ownership.md') as $line) {
    if (preg_match('/^\| `([^`]+\\\\[^`]+)` \| `([a-z][a-z0-9_]*)` \|/', $line, $mapping)) {
        $externalModelTables[$mapping[1]] = $mapping[2];

        continue;
    }

    if (! preg_match('/^\| ([^|]+) \| ([a-z][a-z0-9-]*\/[a-z][a-z0-9-]*) \| ([^|]*) \| ([^|]*) \|/', $line, $m)) {
        continue;
    }
    $catalog[trim($m[1])] = ['owner' => trim($m[2]), 'writers' => array_filter(array_map('trim', explode(',', $m[4])))];
}

if ($catalog === []) {
    fwrite(STDERR, "DATA-003: empty table ownership catalog\n");
    exit(2);
}
foreach ($externalModelTables as $model => $table) {
    if (! isset($catalog[$table])) {
        fwrite(STDERR, "DATA-003: ORM mapping {$model} refers to an uncatalogued table {$table}\n");
        exit(2);
    }
}

$fixture = ($argv[1] ?? '') === 'fixture' ? ($argv[2] ?? '') : null;

if ($fixture !== null && ! in_array($fixture, ['foreign-table-write', 'foreign-helper-write'], true)) {
    fwrite(STDERR, "DATA-003: unknown fixture\n");
    exit(2);
}
$files = [];

if ($fixture !== null) {
    $files[$root.'/tests/fixtures/table-write-ownership/'.$fixture.'.php'] = 'axiomasoft/corex-tenancy';
} else {
    foreach (glob($root.'/packages/*/composer.json') ?: [] as $composerFile) {
        $data = json_decode((string) file_get_contents($composerFile), true);
        $owner = $data['name'] ?? null;

        if (! is_string($owner)) {
            continue;
        }
        $source = dirname($composerFile).'/src';

        if (! is_dir($source)) {
            continue;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[$file->getPathname()] = $owner;
            }
        }
    }
}
ksort($files);

$parser = (new ParserFactory)->createForNewestSupportedVersion();
$finder = new NodeFinder;
$trees = [];
$modelTables = $externalModelTables;
$tableDefaults = [];
foreach ($files as $file => $owner) {
    try {
        $tree = $parser->parse((string) file_get_contents($file));
    } catch (Throwable $e) {
        fwrite(STDERR, "DATA-003: cannot parse {$file}: {$e->getMessage()}\n");
        exit(2);
    }
    $traverser = new NodeTraverser(new NameResolver, new ParentConnectingVisitor);
    $trees[$file] = $traverser->traverse($tree ?? []);
    foreach ($finder->findInstanceOf($trees[$file], Expr\BinaryOp\Coalesce::class) as $choice) {
        if (! $choice->right instanceof Node\Scalar\String_) {
            continue;
        }
        for ($parent = $choice->getAttribute('parent'); $parent instanceof Node; $parent = $parent->getAttribute('parent')) {
            if ($parent instanceof Expr\Assign && $parent->var instanceof Expr\Variable && is_string($parent->var->name)) {
                $slot = $parent->var->name;

                if (str_ends_with($slot, 'Table')) {
                    $tableDefaults[$owner][$slot] = $choice->right->value;
                }

                break;
            }

            if ($parent instanceof Node\Arg && $parent->name instanceof Node\Identifier) {
                $new = $parent->getAttribute('parent');

                if ($new instanceof Expr\New_ && $new->class instanceof Node\Name && $new->class->getLast() === 'AttributeTables') {
                    $tableDefaults[$owner][$parent->name->toString()] = $choice->right->value;
                }

                break;
            }
        }
    }
    foreach ($finder->findInstanceOf($trees[$file], Node\Stmt\Class_::class) as $class) {
        $name = $class->namespacedName?->toString();

        if ($name === null || ! str_contains($name, '\\Models\\')) {
            continue;
        }
        foreach ($class->getProperties() as $property) {
            if ($property->props[0]->name->toString() === 'table' && $property->props[0]->default instanceof Node\Scalar\String_) {
                $modelTables[$name] = $property->props[0]->default->value;
            }
        }

        if (! isset($modelTables[$name])) {
            $modelTables[$name] = $class->getMethod('getTable') === null
                ? Str::snake(Str::pluralStudly($class->name->toString()))
                : '';
        }
    }
}

// Resolve defaults by the declared options FQN, including cross-package consumers.
// A property with the same spelling on an unrelated object remains unresolved.
$optionOwners = [
    'CoreX\Config\Data\CoreOptions' => 'axiomasoft/corex',
    'CoreX\Access\Laravel\AuthOptions' => 'axiomasoft/corex-access',
    'CoreX\CustomFields\Data\AttributeTables' => 'axiomasoft/corex-custom-fields',
];
$typedDefaults = [];
foreach ($optionOwners as $type => $owner) {
    foreach ($tableDefaults[$owner] ?? [] as $slot => $table) {
        $typedDefaults[$type.'.'.$slot] = $table;
    }
}
foreach ($trees as $tree) {
    foreach ($finder->findInstanceOf($tree, Node\Stmt\Class_::class) as $class) {
        $name = $class->namespacedName?->toString();

        if ($name === null || ! isset($optionOwners[$name])) {
            continue;
        }
        foreach ($class->getMethod('__construct')->params ?? [] as $param) {
            if ($param->var instanceof Expr\Variable && is_string($param->var->name)
                && $param->flags !== 0 && $param->default instanceof Node\Scalar\String_) {
                $typedDefaults[$name.'.'.$param->var->name] = $param->default->value;
            }
        }
    }
}
foreach ($files as $owner) {
    $tableDefaults[$owner] = array_merge($tableDefaults[$owner] ?? [], $typedDefaults);
}

// Trait and inherited methods are not on Class_::getMethod(). Index return types
// so `$this->coreOptions()->settingsTable` resolves like a promoted Options property.
$methodReturnTypes = [];
foreach ($trees as $tree) {
    foreach ($finder->findInstanceOf($tree, Node\Stmt\ClassLike::class) as $classLike) {
        $typeName = $classLike->namespacedName?->toString();

        if ($typeName === null) {
            continue;
        }
        foreach ($classLike->getMethods() as $method) {
            $returnType = typeName($method->returnType);

            if ($returnType !== null) {
                $methodReturnTypes[$typeName.'::'.$method->name->toString()] = $returnType;
            }
        }
    }
}

// Resolve model getTable() only after all package option defaults have been indexed.
foreach ($trees as $file => $tree) {
    foreach ($finder->findInstanceOf($tree, Node\Stmt\Class_::class) as $class) {
        $name = $class->namespacedName?->toString();

        if ($name === null || ! str_contains($name, '\\Models\\') || $class->getMethod('getTable') === null) {
            continue;
        }
        foreach ($finder->findInstanceOf($class->getMethod('getTable')->stmts ?? [], Node\Stmt\Return_::class) as $return) {
            $modelTables[$name] = tableExpression($return->expr, $return, $finder, $tableDefaults[$files[$file]] ?? []);

            break;
        }
    }
}

$violations = [];
$checked = 0;
foreach ($trees as $file => $tree) {
    $owner = $files[$file];
    foreach ($finder->find($tree, static fn (Node $n): bool => $n instanceof Expr\MethodCall || $n instanceof Expr\StaticCall) as $call) {
        $method = $call->name instanceof Node\Identifier ? $call->name->toString() : null;

        if ($method === null || ! in_array($method, ['insert', 'insertGetId', 'insertOrIgnore', 'upsert', 'update', 'updateOrInsert', 'delete', 'truncate', 'increment', 'decrement', 'create', 'firstOrCreate', 'updateOrCreate', 'save', 'forceDelete', 'statement', 'affectingStatement'], true)) {
            continue;
        }
        $table = null;

        if (in_array($method, ['statement', 'affectingStatement'], true)
            || ($call instanceof Expr\StaticCall && in_array($method, ['insert', 'update', 'delete'], true) && $call->class instanceof Node\Name && in_array($call->class->getLast(), ['DB', 'Database'], true))) {
            $table = sqlWriteTable($call->args[0]->value ?? null, $method, $call, $finder, $tableDefaults[$owner] ?? []);
        } elseif ($call instanceof Expr\MethodCall) {
            $table = tableFromReceiver($call->var, $call, $finder, $modelTables, $tableDefaults[$owner] ?? []);
        } elseif ($call->class instanceof Node\Name) {
            $table = $modelTables[$call->class->toString()] ?? null;
        }

        if ($table === null) {
            continue; // No statically identified database receiver.
        }
        $checked++;
        $relative = substr($file, strlen($root) + 1);

        foreach (explode('|', $table) as $candidate) {
            if ($candidate === '') {
                $violations[] = "DATA-003 {$relative}:{$call->getStartLine()} dynamic table name on write";
            } elseif (! isset($catalog[$candidate])) {
                $violations[] = "DATA-003 {$relative}:{$call->getStartLine()} table '{$candidate}' missing from catalog";
            } elseif ($catalog[$candidate]['owner'] !== $owner && ! in_array($owner, $catalog[$candidate]['writers'], true)) {
                $violations[] = "DATA-003 {$relative}:{$call->getStartLine()} {$owner} writes '{$candidate}' owned by {$catalog[$candidate]['owner']}";
            }
        }
    }
}

sort($violations);
foreach ($violations as $violation) {
    fwrite(STDERR, $violation."\n");
}

if ($fixture !== null) {
    if (count($violations) !== 1 || ! str_contains($violations[0], "writes 'sys_settings' owned by axiomasoft/corex")) {
        fwrite(STDERR, "DATA-003 fixture did not fail for the expected foreign write\n");
        exit(2);
    }
    exit(1);
}

if ($violations !== []) {
    fwrite(STDERR, "DATA-003: {$checked} resolved write sites, ".count($violations)." violations\n");
    exit(1);
}
echo "DATA-003: OK ({$checked} statically resolved writes)\n";

/** @param array<string,string> $modelTables
 * @param  array<string,string>  $tableDefaults
 */
function tableFromReceiver(Expr $receiver, Node $site, NodeFinder $finder, array $modelTables, array $tableDefaults, int $depth = 0): ?string
{
    if ($depth > 12) {
        return '';
    }

    if ($receiver instanceof Expr\MethodCall) {
        $method = $receiver->name instanceof Node\Identifier ? $receiver->name->toString() : '';

        if ($method === 'table') {
            $arg = $receiver->args[0]->value ?? null;

            return tableExpression($arg, $site, $finder, $tableDefaults, $depth + 1);
        }

        if ($receiver->var instanceof Expr\Variable && $receiver->var->name === 'this') {
            $class = enclosingClass($site);

            if ($class !== null) {
                foreach ($class->getMethods() as $candidate) {
                    if ($candidate->name->toString() === $method) {
                        foreach ($finder->findInstanceOf($candidate->stmts ?? [], Node\Stmt\Return_::class) as $return) {
                            if ($return->expr instanceof Expr) {
                                return tableFromReceiver($return->expr, $return, $finder, $modelTables, $tableDefaults, $depth + 1);
                            }
                        }
                    }
                }
            }
        }

        return tableFromReceiver($receiver->var, $site, $finder, $modelTables, $tableDefaults, $depth + 1);
    }

    if ($receiver instanceof Expr\StaticCall && $receiver->class instanceof Node\Name) {
        if ($receiver->name instanceof Node\Identifier && $receiver->name->toString() === 'table') {
            return tableExpression($receiver->args[0]->value ?? null, $site, $finder, $tableDefaults, $depth + 1);
        }

        return $modelTables[$receiver->class->toString()] ?? null;
    }

    if ($receiver instanceof Expr\New_ && $receiver->class instanceof Node\Name) {
        return $modelTables[$receiver->class->toString()] ?? null;
    }

    if ($receiver instanceof Expr\Variable && is_string($receiver->name)) {
        $scope = enclosingFunction($site);

        if ($scope !== null) {
            $assignments = $finder->find($scope->stmts ?? [], static fn (Node $n): bool => $n instanceof Expr\Assign && $n->var instanceof Expr\Variable && $n->var->name === $receiver->name && $n->getStartLine() < $site->getStartLine());

            if ($assignments !== []) {
                $assignment = end($assignments);

                return tableFromReceiver($assignment->expr, $site, $finder, $modelTables, $tableDefaults, $depth + 1);
            }
            foreach ($scope->params as $param) {
                if ($param->var->name === $receiver->name && $param->type instanceof Node\Name) {
                    return $modelTables[$param->type->toString()] ?? null;
                }
            }
        }
    }

    return null;
}

/** @param array<string,string> $tableDefaults */
function tableExpression(?Expr $expr, Node $site, NodeFinder $finder, array $tableDefaults, int $depth = 0): string
{
    if ($depth > 12) {
        return '';
    }

    if ($expr instanceof Node\Scalar\String_) {
        return $expr->value;
    }

    if ($expr instanceof Expr\ClassConstFetch && $expr->class instanceof Node\Name && $expr->name instanceof Node\Identifier) {
        $class = enclosingClass($site);

        if ($class !== null && in_array($expr->class->toLowerString(), ['self', 'static'], true)) {
            foreach ($class->getConstants() as $constant) {
                foreach ($constant->consts as $value) {
                    if ($value->name->toString() === $expr->name->toString()) {
                        return tableExpression($value->value, $site, $finder, $tableDefaults, $depth + 1);
                    }
                }
            }
        }
    }

    if ($expr instanceof Expr\Variable && is_string($expr->name)) {
        $scope = enclosingFunction($site);

        if ($scope !== null) {
            $assignments = $finder->find($scope->stmts ?? [], static fn (Node $n): bool => $n instanceof Expr\Assign && $n->var instanceof Expr\Variable && $n->var->name === $expr->name && $n->getStartLine() < $site->getStartLine());

            if ($assignments !== []) {
                return tableExpression(end($assignments)->expr, $site, $finder, $tableDefaults, $depth + 1);
            }

            if ($scope instanceof Expr\Closure) {
                foreach ($scope->uses as $use) {
                    if ($use->var->name === $expr->name) {
                        return tableExpression($expr, $scope, $finder, $tableDefaults, $depth + 1);
                    }
                }
            }
            foreach ($scope->params as $index => $param) {
                if ($param->var->name !== $expr->name || ! $scope instanceof Node\Stmt\ClassMethod) {
                    continue;
                }
                $class = enclosingClass($scope);

                if ($class === null) {
                    break;
                }
                $values = [];
                foreach ($finder->findInstanceOf($class->stmts, Expr\MethodCall::class) as $invocation) {
                    if (! $invocation->var instanceof Expr\Variable || $invocation->var->name !== 'this'
                        || ! $invocation->name instanceof Node\Identifier || $invocation->name->toString() !== $scope->name->toString()) {
                        continue;
                    }
                    $value = tableExpression($invocation->args[$index]->value ?? null, $invocation, $finder, $tableDefaults, $depth + 1);
                    $values[] = $value;
                }

                return $values === [] || in_array('', $values, true) ? '' : implode('|', array_unique($values));
            }
        }
    }

    if ($expr instanceof Expr\PropertyFetch && $expr->name instanceof Node\Identifier) {
        $slot = $expr->name->toString();

        if ($expr->var instanceof Expr\Variable && $expr->var->name === 'this' && $slot === 'table') {
            $class = enclosingClass($site);
            $constructor = $class?->getMethod('__construct');

            if ($constructor !== null) {
                foreach ($finder->findInstanceOf($constructor->stmts ?? [], Expr\FuncCall::class) as $guard) {
                    if (! $guard->name instanceof Node\Name || $guard->name->toLowerString() !== 'in_array'
                        || ! ($guard->args[0]->value ?? null) instanceof Expr\Variable
                        || $guard->args[0]->value->name !== 'table'
                        || ! ($guard->args[1]->value ?? null) instanceof Expr\Array_) {
                        continue;
                    }
                    $values = array_map(static fn (Expr\ArrayItem $item): string => tableExpression($item->value, $site, $finder, $tableDefaults, $depth + 1), $guard->args[1]->value->items);

                    return in_array('', $values, true) ? '' : implode('|', array_unique($values));
                }
            }
        }

        $sourceType = null;

        if ($expr->var instanceof Expr\PropertyFetch && $expr->var->var instanceof Expr\Variable && $expr->var->var->name === 'this' && $expr->var->name instanceof Node\Identifier) {
            $sourceType = promotedPropertyType(enclosingClass($site), $expr->var->name->toString());
        } elseif ($expr->var instanceof Expr\MethodCall && $expr->var->var instanceof Expr\Variable && $expr->var->var->name === 'this' && $expr->var->name instanceof Node\Identifier) {
            $sourceType = methodReturnType(enclosingClass($site), $expr->var->name->toString());
        } elseif ($expr->var instanceof Expr\Variable && is_string($expr->var->name) && $expr->var->name !== 'this') {
            $scope = enclosingFunction($site);

            if ($scope !== null) {
                foreach ($scope->params as $param) {
                    if ($param->var instanceof Expr\Variable && $param->var->name === $expr->var->name) {
                        $sourceType = typeName($param->type);

                        break;
                    }
                }
            }
        } elseif ($expr->var instanceof Expr\FuncCall && $expr->var->name instanceof Node\Name
            && $expr->var->name->toString() === 'app' && ($expr->var->args[0]->value ?? null) instanceof Expr\ClassConstFetch
            && $expr->var->args[0]->value->class instanceof Node\Name
            && $expr->var->args[0]->value->name instanceof Node\Identifier
            && $expr->var->args[0]->value->name->toString() === 'class') {
            $sourceType = $expr->var->args[0]->value->class->toString();
        }

        if ($sourceType !== null) {
            return $tableDefaults[$sourceType.'.'.$slot] ?? '';
        }

        if ($expr->var instanceof Expr\Variable && $expr->var->name === 'this') {
            $class = enclosingClass($site);
            foreach ($class?->getMethod('__construct')->params ?? [] as $param) {
                if ($param->var instanceof Expr\Variable && $param->var->name === $slot
                    && $param->flags !== 0 && $param->default instanceof Expr) {
                    return tableExpression($param->default, $param, $finder, $tableDefaults, $depth + 1);
                }
            }
            foreach ($finder->findInstanceOf($class?->getMethod('__construct')->stmts ?? [], Expr\Assign::class) as $assignment) {
                if ($assignment->var instanceof Expr\PropertyFetch && $assignment->var->var instanceof Expr\Variable
                    && $assignment->var->var->name === 'this' && $assignment->var->name instanceof Node\Identifier
                    && $assignment->var->name->toString() === $slot) {
                    return tableExpression($assignment->expr, $assignment, $finder, $tableDefaults, $depth + 1);
                }
            }
        }

        return '';
    }

    if ($expr instanceof Expr\MethodCall && $expr->name instanceof Node\Identifier && $expr->var instanceof Expr\Variable && $expr->var->name === 'this') {
        $method = enclosingClass($site)?->getMethod($expr->name->toString());

        if ($method !== null) {
            foreach ($finder->findInstanceOf($method->stmts ?? [], Node\Stmt\Return_::class) as $return) {
                return tableExpression($return->expr, $return, $finder, $tableDefaults, $depth + 1);
            }
        }
    }

    return '';
}

function promotedPropertyType(?Node\Stmt\Class_ $class, string $property): ?string
{
    $constructor = $class?->getMethod('__construct');

    if ($constructor !== null) {
        foreach ($constructor->params as $param) {
            if ($param->var->name === $property && $param->flags !== 0) {
                return typeName($param->type);
            }
        }
    }

    foreach ($class?->getProperties() ?? [] as $declaration) {
        foreach ($declaration->props as $declared) {
            if ($declared->name->toString() === $property) {
                return typeName($declaration->type);
            }
        }
    }

    return null;
}

function typeName(?Node $type): ?string
{
    if ($type instanceof Node\NullableType) {
        return typeName($type->type);
    }

    return $type instanceof Node\Name ? $type->toString() : null;
}

function methodReturnType(?Node\Stmt\Class_ $class, string $method): ?string
{
    global $methodReturnTypes;

    if ($class === null) {
        return null;
    }

    $declared = typeName($class->getMethod($method)?->returnType);

    if ($declared !== null) {
        return $declared;
    }

    $own = $class->namespacedName?->toString();

    if ($own !== null && isset($methodReturnTypes[$own.'::'.$method])) {
        return $methodReturnTypes[$own.'::'.$method];
    }

    foreach ($class->getTraitUses() as $use) {
        foreach ($use->traits as $trait) {
            $found = $methodReturnTypes[$trait->toString().'::'.$method] ?? null;

            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}

/** @param array<string,string> $tableDefaults */
function sqlWriteTable(?Expr $sql, string $method, Node $site, NodeFinder $finder, array $tableDefaults): ?string
{
    $prefixes = sqlPrefixes($sql, $site, $finder, $tableDefaults);
    $tables = [];

    foreach ($prefixes as $prefix) {
        if (preg_match('/^\s*(?:INSERT(?:\s+OR\s+\w+)?\s+INTO|UPDATE|DELETE\s+FROM)\s+"?([a-z][a-z0-9_]*)"?/i', $prefix, $m)) {
            $tables[] = $m[1];
        } elseif (preg_match('/^\s*(?:INSERT|UPDATE|DELETE)\b/i', $prefix) || $method !== 'statement') {
            $tables[] = '';
        }
    }

    return $tables === [] ? null : implode('|', array_unique($tables));
}

/** @param array<string,string> $tableDefaults
 * @return list<string>
 */
function sqlPrefixes(?Expr $expr, Node $site, NodeFinder $finder, array $tableDefaults, int $depth = 0): array
{
    if ($depth > 16) {
        return ['?'];
    }

    if ($expr instanceof Expr\BinaryOp\Concat) {
        $result = [];
        foreach (sqlPrefixes($expr->left, $site, $finder, $tableDefaults, $depth + 1) as $left) {
            foreach (sqlPrefixes($expr->right, $site, $finder, $tableDefaults, $depth + 1) as $right) {
                $result[] = $left.$right;
            }
        }

        return array_slice($result, 0, 32);
    }

    if ($expr instanceof Node\Scalar\String_) {
        return [$expr->value];
    }

    $table = tableExpression($expr, $site, $finder, $tableDefaults, $depth + 1);

    return $table === '' ? ['?'] : explode('|', $table);
}

function enclosingFunction(Node $node): ?Node\FunctionLike
{
    for ($parent = $node->getAttribute('parent'); $parent instanceof Node; $parent = $parent->getAttribute('parent')) {
        if ($parent instanceof Node\FunctionLike) {
            return $parent;
        }
    }

    return null;
}

function enclosingClass(Node $node): ?Node\Stmt\Class_
{
    for ($parent = $node->getAttribute('parent'); $parent instanceof Node; $parent = $parent->getAttribute('parent')) {
        if ($parent instanceof Node\Stmt\Class_) {
            return $parent;
        }
    }

    return null;
}
