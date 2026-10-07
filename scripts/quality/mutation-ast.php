<?php

declare(strict_types=1);

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Declare_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\GroupUse;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Nop;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use PhpParser\PrettyPrinter\Standard;

require dirname(__DIR__, 2).'/vendor/autoload.php';

// Literal null/string/empty-array defaults only store data. Boolean/numeric defaults
// remain subject to the default value mutators; object construction is executable.
function immutableLiteral(?Expr $value): bool
{
    return $value === null || $value instanceof String_
        || ($value instanceof ConstFetch && $value->name->toLowerString() === 'null')
        || ($value instanceof Array_ && $value->items === []);
}

function declarationDefault(?Expr $value): bool
{
    return immutableLiteral($value) || $value instanceof Int_ || $value instanceof Float_
        || ($value instanceof ConstFetch && in_array($value->name->toLowerString(), ['true', 'false'], true));
}

// Conservative: an empty abstract base with executable methods is still behavior.
/** @param array<Node> $nodes */
function declarationOnly(array $nodes): bool
{
    if ($nodes === []) {
        return false;
    }
    foreach ($nodes as $node) {
        if ($node instanceof Namespace_) {
            if (! declarationOnly($node->stmts)) {
                return false;
            }

            continue;
        }

        if ($node instanceof Declare_) {
            if ($node->stmts !== null) {
                return false;
            }

            continue;
        }

        if ($node instanceof Use_ || $node instanceof GroupUse || $node instanceof Nop) {
            continue;
        }

        if ($node instanceof Interface_) {
            foreach ($node->stmts as $member) {
                if (! $member instanceof ClassMethod || $member->stmts !== null) {
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

        if ($node instanceof Enum_) {
            foreach ($node->stmts as $member) {
                if (! $member instanceof EnumCase || ($member->expr !== null && ! $member->expr instanceof String_ && ! $member->expr instanceof Int_)) {
                    return false;
                }
            }

            continue;
        }

        if (! $node instanceof Class_) {
            return false;
        }

        if ($node->isAbstract()) {
            foreach ($node->stmts as $member) {
                // Trait composition in an otherwise empty abstract extension declares
                // inherited behavior; the executable trait bodies belong to their source files.
                if ($member instanceof TraitUse && $member->adaptations === []) {
                    continue;
                }

                if (! $member instanceof ClassMethod || ! $member->isAbstract() || $member->stmts !== null) {
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
            if ($member instanceof ClassConst) {
                foreach ($member->consts as $constant) {
                    if (! $constant->value instanceof String_) {
                        return false;
                    }
                }

                continue;
            }

            if ($member instanceof Property) {
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

            if (! $member instanceof ClassMethod || $member->name->toLowerString() !== '__construct') {
                return false;
            }
            foreach ($member->params as $parameter) {
                if (! immutableLiteral($parameter->default)) {
                    return false;
                }
            }
            // Storage assignment is allowed only from the corresponding constructor value.
            foreach ($member->stmts ?? [] as $statement) {
                $expression = $statement instanceof Expression ? $statement->expr : null;

                if (! $expression instanceof Assign || ! $expression->var instanceof PropertyFetch
                    || ! $expression->var->var instanceof Variable || $expression->var->var->name !== 'this'
                    || ! $expression->expr instanceof Variable || ! is_string($expression->expr->name)
                    || ! $expression->var->name instanceof Identifier || $expression->var->name->toString() !== $expression->expr->name) {
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
