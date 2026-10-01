<?php

declare(strict_types=1);
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

require 'vendor/autoload.php';
$map = json_decode(file_get_contents('plans/2026.09.27-№1-COREX-KERNEL/artifacts/P04.2-execution/move-map.json'), true, flags: JSON_THROW_ON_ERROR);
$parser = (new ParserFactory)->createForNewestSupportedVersion();
$printer = new Standard;
foreach ($map['moves'] as $row) {
    if ($row['verdict'] !== 'replace' || ! str_contains($row['public_target'], '\\Contracts\\')) {
        continue;
    }
    $port = $row['public_target'];
    $package = substr($row['path'], 0, strpos($row['path'], '/src/'));
    $manifest = json_decode(file_get_contents($package.'/composer.json'), true);
    foreach ($manifest['autoload']['psr-4'] as $ns => $path) {
        if (rtrim($path, '/') === 'src') {
            $prefix = $ns;
        }
    }
    $target = $package.'/src/'.str_replace('\\', '/', substr($port, strlen($prefix))).'.php';

    if (is_file($target)) {
        continue;
    }
    $nodes = (new NodeTraverser(new NameResolver))->traverse($parser->parse(file_get_contents($row['target_path'])));
    $class = (new NodeFinder)->findFirstInstanceOf($nodes, Node\Stmt\Class_::class);
    $methods = [];
    foreach ($class->getMethods() as $method) {
        if (! $method->isPublic() || $method->name->toString() === '__construct') {
            continue;
        }
        $method->stmts = null;
        $method->flags = Node\Stmt\Class_::MODIFIER_PUBLIC | ($method->isStatic() ? Node\Stmt\Class_::MODIFIER_STATIC : 0);
        $method->attrGroups = [];
        $methods[] = $method;
    }
    $name = substr($port, strrpos($port, '\\') + 1);
    $namespace = substr($port, 0, strrpos($port, '\\'));
    $interface = new Node\Stmt\Interface_($name, ['stmts' => $methods]);
    $layer = str_contains($port, 'ModuleCompilation') || str_contains($port, 'ModuleDiscovery') || str_contains($port, 'SearchDriverRegistry') ? 'Implement' : 'Call';
    $text = "<?php\n\ndeclare(strict_types=1);\n\nnamespace $namespace;\n\n#[\\CoreX\\Attributes\\Spi(kind: \\CoreX\\Attributes\\SpiKind::$layer, since: '1.0')]\n".$printer->prettyPrint([$interface])."\n";

    if (! is_dir(dirname($target))) {
        mkdir(dirname($target), 0777, true);
    }file_put_contents($target, $text);
    $source = file_get_contents($row['target_path']);
    $source = preg_replace('/(final (?:readonly )?class '.preg_quote($name, '/').'|final (?:readonly )?class '.preg_quote(basename($row['target_path'], '.php'), '/').')(\s*\{)/', '$1 implements \\'.$port.'$2', $source, 1);
    file_put_contents($row['target_path'],$source);
    echo $port."\n";
}
