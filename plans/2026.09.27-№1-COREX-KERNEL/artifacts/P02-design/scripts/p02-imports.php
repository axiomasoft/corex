<?php

declare(strict_types=1);
use Composer\Autoload\ClassLoader;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/** Preserve implicit same-namespace references when their caller moves. */
// The root autoloader still points at the preimage until Composer is refreshed.
// Load only the parser, without executing obsolete package autoload-files.
require 'vendor/composer/ClassLoader.php';
$loader = new ClassLoader;
$loader->addPsr4('PhpParser\\', 'vendor/nikic/php-parser/lib/PhpParser');
$loader->register();

$plan = json_decode(file_get_contents('build/p02/plan.json'), true, flags: JSON_THROW_ON_ERROR);
$moves = array_column($plan['moves'], 1, 0);
$parser = (new ParserFactory)->createForNewestSupportedVersion();
$changed = 0;
$references = [];
foreach ($plan['class_sources'] as $class => $oldFile) {
    $newFile = $moves[$oldFile] ?? $oldFile;

    if (! is_file($newFile)) {
        continue;
    }
    $oldNamespace = substr($class, 0, strrpos($class, '\\'));
    $newClass = $plan['all_classes'][$class];
    $newNamespace = substr($newClass, 0, strrpos($newClass, '\\'));

    if ($oldNamespace === $newNamespace) {
        continue;
    }
    $source = shell_exec('git show '.escapeshellarg('HEAD:'.$oldFile));
    $nodes = $parser->parse($source);
    $traverser = new NodeTraverser;
    $traverser->addVisitor(new NameResolver(options: ['replaceNodes' => false]));
    $nodes = $traverser->traverse($nodes);
    $imports = [];
    foreach ((new NodeFinder)->findInstanceOf($nodes, Name::class) as $name) {
        $resolved = $name->getAttribute('resolvedName');

        if (! $resolved instanceof Name || ! $name->isUnqualified()) {
            continue;
        }
        $original = $resolved->toString();

        if (! isset($plan['all_classes'][$original]) || $original === $class
            || $original !== $oldNamespace.'\\'.$name->toString()) {
            continue;
        }
        $final = $plan['all_classes'][$original];

        if ($final !== $newNamespace.'\\'.$name->toString()) {
            $imports[$final] = true;
            $references[$newFile.'|'.$final] = ['source' => $newFile, 'symbol' => $final];
        }
    }
    $current = file_get_contents($newFile);
    foreach (array_keys($imports) as $import) {
        if (preg_match('/^use\s+'.preg_quote($import, '/').'\s*(?:as\s+\w+)?;/m', $current) !== 1) {
            $current = preg_replace('/^(namespace\s+[^;]+;\n)/m', '$1'."\nuse ".$import.";\n", $current, limit: 1);
            $changed++;
        }
    }
    file_put_contents($newFile, $current);
}
echo 'implicit imports: '.$changed." preserved\n";
file_put_contents('build/p02/implicit-references.json', json_encode(array_values($references), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
