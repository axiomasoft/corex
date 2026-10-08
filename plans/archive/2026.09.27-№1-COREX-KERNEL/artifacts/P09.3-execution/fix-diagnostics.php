<?php

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\Cast\Int_;
use PhpParser\Node\Expr\Cast\String_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

require getcwd().'/vendor/autoload.php';
$parser = (new ParserFactory)->createForNewestSupportedVersion();
$out = __DIR__;
$helpers = [];
foreach (glob($out.'/current-*.json') as $file) {
    $d = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    foreach ($d['files'] as $path => $info) {
        $path = preg_replace('/ \(in context of class .*$/', '', $path);
        $code = file_get_contents($path);
        $ast = $parser->parse($code);
        $nodes = (new NodeFinder)->find($ast, fn ($n) => $n instanceof Expr);
        $edits = [];
        preg_match('/namespace ([^;]+);/', $code, $m);
        $namespace = $m[1];
        $root = explode('\\', $namespace);
        $base = str_contains($path, '/products/') ? getcwd().'/products/flex-crm' : getcwd().'/packages/'.explode('/', substr($path, strlen(getcwd().'/packages/')))[0];
        $helperNs = $root[0].'\\'.$root[1].'\\Internal';
        foreach ($info['messages'] as $error) {
            $line = $error['line'];
            $id = $error['identifier'];
            $message = $error['message'];

            if ($id === 'argument.type' && preg_match('/^Parameter (?:#(\d+) )?\$(\w+) of (?:class ([^ ]+) constructor|(?:(?:static )?method ([^ ]+)::(\w+)\(\)|function (\w+))) expects (.*), (.*) given\.$/', $message, $argError)) {
                $expected = $argError[7];
                $given = $argError[8];
                $method = match ($expected) {
                    'string' => 'text','string|null' => 'nullableText','int' => 'integer','array<string, mixed>' => 'map','list<string>' => 'strings',default => null,
                };

                if ($method !== null && ! str_contains($given, 'string but') && $given !== $expected) {
                    foreach ($nodes as $call) {
                        if (! ($call instanceof CallLike)) {
                            continue;
                        }

                        if ($argError[3] !== '') {
                            if (! ($call instanceof New_) || ! ($call->class instanceof Name)) {
                                continue;
                            }
                            $parts = explode('\\', $argError[3]);

                            if ($call->class->getLast() !== end($parts)) {
                                continue;
                            }
                        }

                        if (($argError[5] ?? '') !== '' && (! isset($call->name) || ! ($call->name instanceof Identifier) || $call->name->name !== $argError[5])) {
                            continue;
                        }

                        if (($argError[6] ?? '') !== '' && (! isset($call->name) || ! ($call->name instanceof Name) || $call->name->toString() !== $argError[6])) {
                            continue;
                        }
                        foreach ($call->getArgs() as $index => $arg) {
                            if ($arg->getStartLine() !== $line) {
                                continue;
                            }

                            if ($arg->name !== null ? $arg->name->name !== $argError[2] : (($argError[1] ?? '') !== '' ? $index !== (int) $argError[1] - 1 : true)) {
                                continue;
                            }
                            $expr = $arg->value;
                            $start = $expr->getStartFilePos();
                            $end = $expr->getEndFilePos();
                            $raw = substr($code, $start, $end - $start + 1);

                            if (! str_starts_with($raw, 'Value::'.$method.'(')) {
                                $edits[$start] = [$end, 'Value::'.$method.'('.$raw.')'];
                            }
                        }
                    }
                }
            }
            foreach ($nodes as $node) {
                if ($node->getStartLine() !== $line) {
                    continue;
                }
                $start = $node->getStartFilePos();
                $end = $node->getEndFilePos();
                $raw = substr($code, $start, $end - $start + 1);
                $replacement = null;

                if ($node instanceof Cast && in_array($id, ['cast.string', 'cast.int'], true)) {
                    if (($id === 'cast.string' && $node instanceof String_) || ($id === 'cast.int' && $node instanceof Int_)) {
                        $expr = $node->expr;
                        $value = substr($code, $expr->getStartFilePos(), $expr->getEndFilePos() - $expr->getStartFilePos() + 1);
                        $replacement = 'Value::'.($id === 'cast.string' ? 'text' : 'integer').'('.$value.')';
                    }
                }

                if ($node instanceof Cast && $id === 'cast.useless') {
                    if (($node instanceof String_ && str_contains($message, 'Casting to string')) || ($node instanceof Int_ && str_contains($message, 'Casting to int'))) {
                        $expr = $node->expr;
                        $replacement = substr($code, $expr->getStartFilePos(), $expr->getEndFilePos() - $expr->getStartFilePos() + 1);
                    }
                }

                if ($node instanceof BooleanNot && $id === 'booleanNot.exprNotBoolean' && $node->expr instanceof FuncCall && $node->expr->name instanceof Name && $node->expr->name->toString() === 'preg_match') {
                    $expr = $node->expr;
                    $replacement = substr($code, $expr->getStartFilePos(), $expr->getEndFilePos() - $expr->getStartFilePos() + 1).' !== 1';
                }

                if ($id === 'property.notFound' && $node instanceof PropertyFetch && $node->name instanceof Identifier && str_contains($message, '::$'.$node->name->name.'.')) {
                    $expr = $node->var;
                    $replacement = 'Value::field('.substr($code, $expr->getStartFilePos(), $expr->getEndFilePos() - $expr->getStartFilePos() + 1).", '".$node->name->name."')";
                }

                if ($replacement !== null) {
                    $edits[$start] = [$end, $replacement];
                }
            }
        }
        // Apply only disjoint leaf edits; outer expressions will be diagnosed again.
        $selected = [];
        ksort($edits);
        foreach ($edits as $start => $edit) {
            $overlap = false;
            foreach ($edits as $inner => $other) {
                if ($inner > $start && $other[0] <= $edit[0]) {
                    $overlap = true;

                    break;
                }
            }

            if (! $overlap) {
                $selected[$start] = $edit;
            }
        }
        krsort($selected);
        foreach ($selected as $start => [$end,$replacement]) {
            $code = substr_replace($code, $replacement, $start, $end - $start + 1);
        }

        if ($selected) {
            if (str_contains($code, 'Value::')) {
                if ($namespace !== $helperNs && ! str_contains($code, 'use '.$helperNs.'\\Value;')) {
                    $code = str_replace('namespace '.$namespace.';', 'namespace '.$namespace.";\n\nuse ".$helperNs.'\\Value;', $code);
                }
                $helpers[$base] = [$helperNs, $namespace];
            }
            file_put_contents($path, $code);
            echo basename($path).' '.count($selected)."\n";
        }
    }
}
foreach ($helpers as $base => [$ns]) {
    $path = $base.'/src/Internal/Value.php';

    if (file_exists($path)) {
        continue;
    }

    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0777, true);
    }
    $code = <<<'SRC'
<?php

declare(strict_types=1);

namespace HELPER_NAMESPACE;

use RuntimeException;
use Stringable;

/** @internal Validate untyped persistence and transport values at the package boundary. */
final class Value
{
    public static function field(object $row, string $name): mixed
    {
        $fields = get_object_vars($row);
        if (! array_key_exists($name, $fields)) {
            throw new RuntimeException('Missing stored field: '.$name);
        }
        return $fields[$name];
    }

    public static function text(mixed $value): string
    {
        if (! is_string($value) && ! is_int($value) && ! $value instanceof Stringable) {
            throw new RuntimeException('Expected a text value.');
        }
        return (string) $value;
    }

    public static function nullableText(mixed $value): ?string
    {
        return $value === null ? null : self::text($value);
    }

    public static function integer(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value) === 1) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
            if (is_int($integer)) {
                return $integer;
            }
        }
        throw new RuntimeException('Expected a database integer within the native range.');
    }

    /** @return array<string, mixed> */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            throw new RuntimeException('Expected a map.');
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new RuntimeException('Expected string map keys.');
            }
            $result[$key] = $item;
        }
        return $result;
    }

    /** @return list<string> */
    public static function strings(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new RuntimeException('Expected a string list.');
        }
        $result = [];
        foreach ($value as $item) {
            $result[] = self::text($item);
        }
        return $result;
    }
}
SRC;
    file_put_contents($path, str_replace('HELPER_NAMESPACE', $ns, $code)."\n");
}
