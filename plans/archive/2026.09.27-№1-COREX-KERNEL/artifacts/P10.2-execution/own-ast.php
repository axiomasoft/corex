<?php

declare(strict_types=1);
use PhpParser\Modifiers;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

require getcwd().'/vendor/autoload.php';
$root = $argv[1];
$parser = (new ParserFactory)->createForNewestSupportedVersion();
$printer = new Standard;
$inventory = [];
foreach (glob($root.'/packages/*/composer.json') as $manifest) {
    $package = json_decode(file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR)['name'];
    $inventory[$package] = [];
    $directory = dirname($manifest).'/src';

    if (! is_dir($directory)) {
        continue;
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $nodes = (new NodeTraverser(new NameResolver))->traverse($parser->parse(file_get_contents($file->getPathname())));
        foreach ((new NodeFinder)->findInstanceOf($nodes, ClassLike::class) as $class) {
            if ($class->name === null) {
                continue;
            }
            $protected = false;
            $marked = false;
            foreach ($class->attrGroups as $group) {
                foreach ($group->attrs as $attribute) {
                    if (in_array($attribute->name->toString(), ['CoreX\\Attributes\\Api', 'CoreX\\Attributes\\Spi'], true)) {
                        $marked = true;
                        foreach ($attribute->args as $argument) {
                            if ($argument->value instanceof ClassConstFetch && $argument->value->name->toString() === 'Implement') {
                                $protected = true;
                            }
                        }
                    }
                }
            }

            if (! $marked) {
                continue;
            }
            $symbol = $class->namespacedName->toString();
            $inventory[$package][$symbol] = [];
            foreach ($class->stmts as $node) {
                if ($node instanceof ClassMethod) {
                    foreach ($node->params as $param) {
                        if ($param->flags !== 0 && ($param->flags & Modifiers::PRIVATE) === 0 && (($param->flags & Modifiers::PROTECTED) === 0 || $protected)) {
                            $inventory[$package][$symbol]['properties'][$param->var->name] = $printer->prettyPrint([$param]);
                        }
                    }
                }

                if (($node instanceof ClassMethod || $node instanceof Property || $node instanceof ClassConst) && ! $node->isPrivate() && (! $node->isProtected() || $protected)) {
                    $copy = clone $node;

                    if ($copy instanceof ClassMethod) {
                        $copy->stmts = null;
                        $inventory[$package][$symbol]['methods'][$copy->name->toString()] = $printer->prettyPrint([$copy]);
                    } else {
                        $category = $copy instanceof Property ? 'properties' : 'constants';
                        foreach ($copy instanceof Property ? $copy->props : $copy->consts as $member) {
                            $inventory[$package][$symbol][$category][$member->name->toString()] = $printer->prettyPrint([$copy]);
                        }
                    }
                } elseif ($node instanceof EnumCase) {
                    $inventory[$package][$symbol]['enum_cases'][$node->name->toString()] = $printer->prettyPrint([$node]);
                }
            }
            foreach ($inventory[$package][$symbol] as &$members) {
                ksort($members);
            }
            unset($members);
            ksort($inventory[$package][$symbol]);
        }
    }
    ksort($inventory[$package]);
}
ksort($inventory);
echo json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
