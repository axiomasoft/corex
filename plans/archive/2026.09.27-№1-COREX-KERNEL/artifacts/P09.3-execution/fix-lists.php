<?php

use PhpParser\Node\Stmt\Return_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

require getcwd().'/vendor/autoload.php';
$parser = (new ParserFactory)->createForNewestSupportedVersion();
foreach (glob(__DIR__.'/current-*.json') as $file) {
    $d = json_decode(file_get_contents($file), true);
    foreach ($d['files'] as $path => $info) {
        $path = preg_replace('/ \(in context of class .*$/', '', $path);
        $code = file_get_contents($path);
        $ast = $parser->parse($code);
        $finder = new NodeFinder;
        $edits = [];
        foreach ($info['messages'] as $e) {
            if ($e['identifier'] === 'return.type' && str_contains($e['message'], 'should return list<') && str_contains($e['message'], 'but returns array<')) {
                foreach ($finder->findInstanceOf($ast, Return_::class) as $r) {
                    if ($r->getStartLine() !== $e['line'] || ! $r->expr) {
                        continue;
                    }
                    $n = $r->expr;
                    $start = $n->getStartFilePos();
                    $end = $n->getEndFilePos();
                    $edits[$start] = [$end, 'array_values('.substr($code, $start, $end - $start + 1).')'];
                }
            }
        }
        krsort($edits);
        foreach ($edits as $start => [$end,$text]) {
            $code = substr_replace($code, $text, $start, $end - $start + 1);
        }

        if ($edits) {
            file_put_contents($path, $code);
        }
    }
}
