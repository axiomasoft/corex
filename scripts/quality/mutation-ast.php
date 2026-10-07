<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// Literal null/string/empty-array defaults only store data. Boolean/numeric defaults
// remain subject to the default value mutators; object construction is executable.
function immutableLiteral(?Expr $value): bool
{
    return $value === null || $value instanceof Node\Scalar\String_
        || ($value instanceof Expr\ConstFetch && $value->name->toLowerString() === 'null')
        || ($value instanceof Expr\Array_ && $value->items === []);
}

function declarationDefault(?Expr $value): bool
{
    return immutableLiteral($value) || $value instanceof Node\Scalar\Int_ || $value instanceof Node\Scalar\Float_
        || ($value instanceof Expr\ConstFetch && in_array($value->name->toLowerString(), ['true', 'false'], true));
}

// Conservative: an empty abstract base with executable methods is still behavior.
/** @param array<Node> $nodes */
function declarationOnly(array $nodes): bool
{
    if ($nodes === []) {
        return false;
    }
    foreach ($nodes as $node) {
        if ($node instanceof Stmt\Namespace_) {
            if (! declarationOnly($node->stmts)) {
                return false;
            }

            continue;
        }

        if ($node instanceof Stmt\Declare_) {
            if ($node->stmts !== null) {
                return false;
            }

            continue;
        }

        if ($node instanceof Stmt\Use_ || $node instanceof Stmt\GroupUse || $node instanceof Stmt\Nop) {
            continue;
        }

        if ($node instanceof Stmt\Interface_) {
            foreach ($node->stmts as $member) {
                if (! $member instanceof Stmt\ClassMethod || $member->stmts !== null) {
                    return false;
                }
                foreach ($member->params as $parameter) {
                    if (! declarationDefault($parameter->default)) {
                        return false;
                    }
                }
            }

            continue;
        }

        if ($node instanceof Stmt\Enum_) {
            foreach ($node->stmts as $member) {
                if (! $member instanceof Stmt\EnumCase || ($member->expr !== null && ! $member->expr instanceof Node\Scalar\String_ && ! $member->expr instanceof Node\Scalar\Int_)) {
                    return false;
                }
            }

            continue;
        }

        if (! $node instanceof Stmt\Class_) {
            return false;
        }

        if ($node->isAbstract()) {
            foreach ($node->stmts as $member) {
                // Trait composition in an otherwise empty abstract extension declares
                // inherited behavior; the executable trait bodies belong to their source files.
                if ($member instanceof Stmt\TraitUse && $member->adaptations === []) {
                    continue;
                }

                if (! $member instanceof Stmt\ClassMethod || ! $member->isAbstract() || $member->stmts !== null) {
                    return false;
                }
                foreach ($member->params as $parameter) {
                    if (! declarationDefault($parameter->default)) {
                        return false;
                    }
                }
            }

            continue;
        }

        if ($node->extends !== null || ! $node->isReadonly() || $node->implements !== []) {
            return false;
        }
        foreach ($node->stmts as $member) {
            if ($member instanceof Stmt\ClassConst) {
                foreach ($member->consts as $constant) {
                    if (! $constant->value instanceof Node\Scalar\String_) {
                        return false;
                    }
                }

                continue;
            }

            if ($member instanceof Stmt\Property) {
                if ($member->isStatic()) {
                    return false;
                }
                foreach ($member->props as $property) {
                    if ($property->default !== null) {
                        return false;
                    }
                }

                continue;
            }

            if (! $member instanceof Stmt\ClassMethod || $member->name->toLowerString() !== '__construct') {
                return false;
            }
            foreach ($member->params as $parameter) {
                if (! immutableLiteral($parameter->default)) {
                    return false;
                }
            }
            // Storage assignment is allowed only from the corresponding constructor value.
            foreach ($member->stmts ?? [] as $statement) {
                $expression = $statement instanceof Stmt\Expression ? $statement->expr : null;

                if (! $expression instanceof Expr\Assign || ! $expression->var instanceof Expr\PropertyFetch
                    || ! $expression->var->var instanceof Expr\Variable || $expression->var->var->name !== 'this'
                    || ! $expression->expr instanceof Expr\Variable || ! is_string($expression->expr->name)
                    || ! $expression->var->name instanceof Node\Identifier || $expression->var->name->toString() !== $expression->expr->name) {
                    return false;
                }
            }
        }
    }

    return true;
}

$request = json_decode(stream_get_contents(STDIN), associative: true, flags: JSON_THROW_ON_ERROR);

if (! is_array($request)) {
    throw new RuntimeException('Expected a source map.');
}
$parser = (new ParserFactory)->createForVersion(PhpVersion::fromString('8.4'));
$printer = new Standard;
$rows = [];
foreach ($request as $path => $source) {
    if (! is_string($path) || ! is_string($source)) {
        throw new RuntimeException('Expected string source paths and contents.');
    }
    $tree = $parser->parse($source) ?? [];
    $declaration = declarationOnly($tree);
    $rows[$path] = [
        'sha256' => hash('sha256', $source),
        'normalized_sha256' => hash('sha256', $printer->prettyPrintFile($tree)),
        'declaration_only' => $declaration,
        'reason' => $declaration ? 'PHPParser: declarations or immutable storage; no executable value expressions or branches' : null,
    ];
}
echo json_encode($rows, flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
