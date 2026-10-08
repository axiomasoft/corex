<?php

declare(strict_types=1);

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

require 'vendor/autoload.php';
$map = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
$classes = $map['classes'];
$paths = array_column($map['moves'], 'target_path', 'path');
$parser = (new ParserFactory)->createForNewestSupportedVersion();
$printer = new Standard;
$files = [];
foreach (['packages', 'products', 'tests', 'fixtures', 'scripts'] as $directory) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = $file->getPathname();

        if ($file->getExtension() === 'php' && ! str_contains($path, '/vendor/') && ! str_contains($path, '/build/') && ! str_contains($path, '/node_modules/') && ! str_contains($path, '/baseline/')) {
            $files[] = $path;
        }
    }
}
foreach ($files as $file) {
    $source = file_get_contents($file);
    $old = $parser->parse($source);
    $tokens = $parser->getTokens();
    $traverser = new NodeTraverser(new CloningVisitor, new NameResolver(options: ['replaceNodes' => false]));
    $nodes = $traverser->traverse($old);
    $namespace = null;
    foreach ($nodes as $node) {
        if ($node instanceof Node\Stmt\Namespace_) {
            $namespace = $node->name?->toString();
        }
    }
    $newNamespace = isset($paths[$file]) ? substr($map['classes'][$namespace.'\\'.basename($file, '.php')], 0, strrpos($map['classes'][$namespace.'\\'.basename($file, '.php')], '\\')) : $namespace;
    $visitor = new class($classes, $namespace, $newNamespace) extends NodeVisitorAbstract
    {
        public function __construct(private array $map, private ?string $oldNs, private ?string $newNs) {}

        public function enterNode(Node $node)
        {
            if ($node instanceof Node\Stmt\Namespace_ && $this->oldNs !== $this->newNs) {
                $node->name = new Node\Name($this->newNs);
            }

            if ($node instanceof Node\Name && ! $node->getAttribute('resolvedName') && isset($this->map[$node->toString()])) {
                return new Node\Name($this->map[$node->toString()], $node->getAttributes());
            }

            if ($node instanceof Node\Name && ($resolved = $node->getAttribute('resolvedName')) instanceof Node\Name) {
                $name = $resolved->toString();

                if (isset($this->map[$name])) {
                    return new Node\Name\FullyQualified($this->map[$name], $node->getAttributes());
                }

                if ($this->oldNs !== $this->newNs && $node->isUnqualified() && str_starts_with($name, $this->oldNs.'\\')) {
                    return new Node\Name\FullyQualified($name, $node->getAttributes());
                }
            }

            if ($node instanceof Node\Scalar\String_ && str_contains($node->value, '\\')) {
                $value = $node->value;
                $node->value = preg_replace_callback('/(?<![A-Za-z0-9_\\\\])\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+/', fn ($m) => ($m[0][0] === '\\' ? '\\' : '').($this->map[ltrim($m[0], '\\')] ?? ltrim($m[0], '\\')), $value);
            }
            foreach ($node->getComments() as $comment) {
                $text = $comment->getText();
                $changed = preg_replace_callback('/(?<![A-Za-z0-9_\\\\])\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+/', fn ($m) => ($m[0][0] === '\\' ? '\\' : '').($this->map[ltrim($m[0], '\\')] ?? ltrim($m[0], '\\')), $text);

                if ($text !== $changed) {
                    $node->setDocComment(new Doc($changed));
                }
            }

            return null;
        }
    };
    $nodes = (new NodeTraverser($visitor))->traverse($nodes);
    $after = $printer->printFormatPreserving($nodes, $old, $tokens);

    if ($source !== $after) {
        file_put_contents($file, $after);
    }
}
foreach ($paths as $old => $new) {
    if (! is_dir(dirname($new))) {
        mkdir(dirname($new), 0777, true);
    }rename($old,$new);
}
echo 'AST paths: '.count($paths)."\n";
