<?php

declare(strict_types=1);

// Read-only design inventory. Never boot Laravel or instantiate source classes.
require getcwd().'/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

$parser = (new ParserFactory)->createForNewestSupportedVersion();
$finder = new NodeFinder;
$printer = new Standard;
$types = [];
$references = [];
$errors = [];
$manifests = [];
foreach (array_merge(glob('packages/*/composer.json'), glob('products/*/composer.json')) as $manifest) {
    $meta = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);
    $base = dirname($manifest);
    $manifests[$meta['name']] = ['path' => $base, 'public_api' => $meta['extra']['corex']['public-api'] ?? []];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base.'/src', FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        $source = file_get_contents($path);
        $ast = $parser->parse($source);
        $traverser = new NodeTraverser(new NameResolver);
        $ast = $traverser->traverse($ast);
        foreach ($finder->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $type) {
            if ($type->name === null) {
                continue;
            }
            $fqcn = (string) $type->namespacedName;
            $members = [];
            foreach ($type->stmts as $member) {
                if ($member instanceof Node\Stmt\ClassMethod && ! $member->isPrivate()) {
                    $copy = clone $member;
                    $copy->stmts = null;
                    $members[] = $printer->prettyPrint([$copy]);
                } elseif ($member instanceof Node\Stmt\Property && ! $member->isPrivate()) {
                    $members[] = $printer->prettyPrint([$member]);
                }
            }
            $types[$fqcn] = ['package' => $meta['name'], 'path' => $path, 'line' => $type->getStartLine(),
                'kind' => $type->getType(), 'internal' => str_contains($fqcn, '\\Internal\\'),
                'abstract' => $type instanceof Node\Stmt\Class_ && $type->isAbstract(),
                'final' => $type instanceof Node\Stmt\Class_ && $type->isFinal(),
                'readonly' => $type instanceof Node\Stmt\Class_ && $type->isReadonly(),
                'parents' => array_map(strval(...), array_filter(array_merge(
                    $type instanceof Node\Stmt\Class_ ? [$type->extends] : [],
                    $type instanceof Node\Stmt\Interface_ ? $type->extends : [],
                    $type instanceof Node\Stmt\Class_ || $type instanceof Node\Stmt\Enum_ ? $type->implements : []))),
                'members' => $members, 'sha256' => hash('sha256', $source)];
        }
        foreach ($finder->findInstanceOf($ast, Node\Name::class) as $name) {
            if ($name instanceof Node\Name\FullyQualified) {
                $references[] = ['package' => $meta['name'], 'path' => $path, 'line' => $name->getStartLine(), 'symbol' => (string) $name];
            }
        }
        foreach ($finder->findInstanceOf($ast, Node\Expr\New_::class) as $new) {
            if (! $new->class instanceof Node\Name || $new->args === []) {
                continue;
            }
            $class = (string) $new->class;
            if (! preg_match('/\\\\(?:ApiError|ErrorDescriptor)$/', $class)) {
                continue;
            }
            $errors[] = ['package' => $meta['name'], 'path' => $path, 'line' => $new->getStartLine(),
                'class' => $class, 'code_expression' => $printer->prettyPrintExpr($new->args[0]->value)];
        }
    }
}
ksort($types);
ksort($manifests);
$cross = array_values(array_filter($references, fn ($r) => isset($types[$r['symbol']]) && $types[$r['symbol']]['package'] !== $r['package']));
$modelTypes = [];
$isModel = function (string $name, array $seen = []) use (&$isModel, &$types): bool {
    if ($name === 'Illuminate\\Database\\Eloquent\\Model' || $name === 'Stancl\\Tenancy\\Database\\Models\\Tenant') {
        return true;
    }
    if (isset($seen[$name]) || ! isset($types[$name])) {
        return false;
    }
    $seen[$name] = true;
    foreach ($types[$name]['parents'] as $parent) {
        if ($isModel($parent, $seen)) {
            return true;
        }
    }
    return false;
};
foreach ($types as $name => $type) {
    if ($isModel($name)) {
        $modelTypes[] = $name;
    }
}
echo json_encode(['schema' => 'p04-source-inventory/1', 'types' => $types, 'cross_references' => $cross,
    'error_sites' => $errors, 'model_types' => $modelTypes, 'manifests' => $manifests], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";
