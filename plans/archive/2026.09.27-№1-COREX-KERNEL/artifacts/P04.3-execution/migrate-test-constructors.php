<?php

declare(strict_types=1);
use CoreX\Errors\Data\ErrorDescriptor;
use CoreX\Errors\Internal\ErrorSourceScanner;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

require getcwd().'/vendor/autoload.php';

$parser = (new ParserFactory)->createForNewestSupportedVersion();
$finder = new NodeFinder;
$catalogue = (new ErrorSourceScanner)->catalogue(getcwd());
$codes = [];
foreach ($catalogue->all() as $definition) {
    $wire = $definition->code->value();
    [$owner, $code] = explode('.', $wire, 2);
    $codes[$code] = $definition->code;
}
$unknown = [];
foreach (['packages', 'tests'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = $file->getPathname();

        if ($file->getExtension() !== 'php' || ! str_contains($path, '/tests/') || str_contains($path, '/vendor/')) {
            continue;
        }
        $source = file_get_contents($path);
        $nodes = (new NodeTraverser(new NameResolver))->traverse($parser->parse($source) ?? []);
        $replacements = [];
        foreach ($finder->findInstanceOf($nodes, New_::class) as $node) {
            if (! $node->class instanceof Name || ! in_array($node->class->toString(), ['CoreX\\Operations\\Errors\\Exceptions\\ApiError', ErrorDescriptor::class], true)) {
                continue;
            }
            foreach ($node->args as $index => $argument) {
                if (! $argument instanceof Arg || ! (($argument->name === null && $index === 0) || in_array($argument->name?->toString(), ['code', 'errorCode'], true))) {
                    continue;
                }

                if (! $argument->value instanceof String_) {
                    continue;
                }
                $code = $argument->value->value;

                if (! isset($codes[$code])) {
                    $unknown[$path][] = $code;

                    continue;
                }
                $identity = $codes[$code];
                $replacements[] = [$argument->value->getStartFilePos(), $argument->value->getEndFilePos(), '\\'.$identity::class.'::'.$identity->name];
            }
        }
        rsort($replacements);
        foreach ($replacements as [$start, $end, $replacement]) {
            $source = substr_replace($source, $replacement, $start, $end - $start + 1);
        }

        if ($replacements !== []) {
            file_put_contents($path, $source);
        }
    }
}
echo json_encode($unknown, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
