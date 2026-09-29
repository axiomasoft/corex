<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Use_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

require 'vendor/autoload.php';

$planPath = $argv[1] ?? 'build/p03/plan.json';
$plan = json_decode(file_get_contents($planPath), true, flags: JSON_THROW_ON_ERROR);
$fixturePath = 'tests/fixtures/public-error-contract.php';
$fixture = require $fixturePath;
$source = file_get_contents($fixturePath);
$inverse = array_flip(array_column($plan['moves'], 1, 0));
$parser = (new ParserFactory)->createForNewestSupportedVersion();
$normalize = function (mixed $node) use (&$normalize, $plan): mixed {
    if (is_array($node)) {
        return array_values(array_map($normalize, array_filter($node, static fn ($value) =>
            ! ($value instanceof Use_ || $value instanceof GroupUse || $value instanceof Node\Stmt\Nop))));
    }
    if ($node instanceof Use_ || $node instanceof GroupUse || $node instanceof Node\Stmt\Nop) {
        return null;
    }
    if ($node instanceof Name) {
        return ['name' => $plan['classes'][$node->toString()] ?? $node->toString()];
    }
    if ($node instanceof Namespace_) {
        return ['namespace_statements' => $normalize($node->stmts)];
    }
    if ($node instanceof Node) {
        $value = ['type' => $node->getType()];
        if ($node instanceof Node\Stmt\ClassLike && $node->namespacedName !== null) {
            $value['declared_name'] = $normalize($node->namespacedName);
        }
        foreach ($node->getSubNodeNames() as $key) {
            $value[$key] = $normalize($node->$key);
        }
        return $value;
    }
    return is_string($node) ? ($plan['classes'][$node] ?? $node) : $node;
};
$ast = static function (string $contents) use ($parser, $normalize): mixed {
    $traverser = new NodeTraverser;
    $traverser->addVisitor(new NameResolver);
    return $normalize($traverser->traverse($parser->parse($contents)));
};
$receipt = [];
foreach ($fixture['dynamic_adapters'] as $adapter) {
    $path = $adapter['source'];
    $oldPath = $inverse[$path] ?? $path;
    $old = shell_exec('git show '.escapeshellarg('HEAD:'.$oldPath));
    $new = file_get_contents($path);
    $hash = hash('sha256', $new);
    if (! in_array($adapter['sha256'], [hash('sha256', $old), $hash], true)) {
        throw new RuntimeException('Untrusted adapter preimage: '.$oldPath);
    }
    if ($ast($old) !== $ast($new)) {
        throw new RuntimeException('Non-mechanical adapter change: '.$path);
    }
    $source = str_replace("'sha256' => '".$adapter['sha256']."'", "'sha256' => '".$hash."'", $source);
    $receipt[] = ['source' => $path, 'before' => hash('sha256', $old), 'after' => $hash, 'ast_equal' => true];
}
file_put_contents($fixturePath, $source);
file_put_contents(dirname($planPath).'/adapter-projection.json', json_encode($receipt, JSON_PRETTY_PRINT)."\n");
echo 'adapter hashes: '.count($receipt)." mechanically equivalent sources\n";
