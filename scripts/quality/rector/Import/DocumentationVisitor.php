<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Import;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\UseItem;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

/** Removes documentation metadata while retaining the native rule implementations. */
final class DocumentationVisitor extends NodeVisitorAbstract
{
    public function leaveNode(Node $node): Node|int|null
    {
        if ($node instanceof ClassMethod && $node->name->toString() === 'getRuleDefinition') {
            return NodeVisitor::REMOVE_NODE;
        }

        if ($node instanceof Use_) {
            $node->uses = array_values(array_filter($node->uses, static fn (UseItem $use): bool => ! str_starts_with($use->name->toString(), 'Symplify\\RuleDocGenerator\\') && ! str_starts_with($use->name->toString(), 'CoreX\\Tooling\\Rector\\Laravel\\Tests\\')));

            if ($node->uses === []) {
                return NodeVisitor::REMOVE_NODE;
            }
        }

        if ($node instanceof Class_) {
            $node->implements = array_values(array_filter($node->implements, static fn (Name $name): bool => $name->toString() !== 'DocumentedRuleInterface'));
        }

        if ($node instanceof Name && ($node->toString() === 'RectorLaravel' || str_starts_with($node->toString(), 'RectorLaravel\\'))) {
            $name = 'CoreX\\Tooling\\Rector\\Laravel'.substr($node->toString(), strlen('RectorLaravel'));

            return $node instanceof FullyQualified ? new FullyQualified($name, $node->getAttributes()) : new Name($name, $node->getAttributes());
        }

        $doc = $node->getDocComment();

        if ($doc !== null && str_contains($doc->getText(), '@see')) {
            $text = preg_replace('/^.*@see.*\R/m', '', $doc->getText());

            if ($text !== null) {
                $node->setDocComment(new Doc($text));
            }
        }

        return null;
    }
}
