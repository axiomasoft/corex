<?php

use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

require getcwd().'/vendor/autoload.php';
$parser = (new ParserFactory)->createForNewestSupportedVersion();
$finder = new NodeFinder;
foreach (array_merge(glob('packages/*/src/Internal/Value.php'), glob('packages/*/src/*/Internal/Value.php'), glob('products/*/src/Internal/Value.php')) as $path) {
    $base = dirname(dirname($path));
    $calls = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php' || $file->getPathname() === $path) {
            continue;
        }
        preg_match_all('/\bValue::(\w+)\(/', file_get_contents($file->getPathname()), $m);
        $calls = array_merge($calls, $m[1]);
    }
    $code = file_get_contents($path);
    $methods = $finder->findInstanceOf($parser->parse($code), ClassMethod::class);
    $dependencies = [];
    foreach ($methods as $method) {
        $raw = substr($code, $method->getStartFilePos(), $method->getEndFilePos() - $method->getStartFilePos() + 1);
        preg_match_all('/self::(\w+)\(/', $raw, $m);
        $dependencies[$method->name->name] = $m[1];
    }
    for ($i = 0; $i < count($calls); $i++) {
        foreach ($dependencies[$calls[$i]] ?? [] as $dependency) {
            if (! in_array($dependency, $calls, true)) {
                $calls[] = $dependency;
            }
        }
    }
    $edits = [];
    foreach ($methods as $method) {
        if (in_array($method->name->name, $calls, true)) {
            continue;
        }
        $start = $method->getStartFilePos();
        foreach ($method->getComments() as $c) {
            $start = min($start, $c->getStartFilePos());
        }
        $edits[$start] = $method->getEndFilePos();
    }
    krsort($edits);
    foreach ($edits as $start => $end) {
        $code = substr_replace($code, '', $start, $end - $start + 1);
    }
    file_put_contents($path, $code);
    echo $path.' '.implode(',', array_unique($calls))."\n";
}
