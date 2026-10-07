<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Import;

use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;

final class SubsetImporter
{
    /** @return array<string, string> */
    public function baseline(SourceSnapshot $snapshot): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $printer = new Standard;
        $outputs = [];

        foreach ([...$snapshot->production, ...$snapshot->configs] as $path) {
            $nodes = $parser->parse($snapshot->files[$path]['text']);

            if ($nodes === null) {
                throw new RuntimeException('Cannot parse input: '.$path);
            }

            $traverser = new NodeTraverser(new DocumentationVisitor);
            $nodes = $traverser->traverse($nodes);
            $outputs['Laravel/'.$path] = $printer->prettyPrintFile($nodes)."\n";
        }

        $outputs['Laravel/LICENSE'] = $snapshot->files['LICENSE']['text'] ?? throw new RuntimeException('Missing MIT attribution');
        $this->assertClosure($outputs, false);

        return $outputs;
    }

    /**
     * @param  array<string, string>  $outputs
     * @return array<string, string>
     */
    public function adjusted(array $outputs, string $path): array
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('Cannot read native adjustments');
        }

        $adjustments = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($adjustments)) {
            throw new RuntimeException('Invalid native adjustments');
        }

        foreach ($adjustments as $file => $adjustment) {
            if (! is_string($file) || ! isset($outputs[$file]) || ! is_array($adjustment) || ! is_string($adjustment['input_sha256'] ?? null) || ! is_string($adjustment['output_sha256'] ?? null) || ! is_array($adjustment['edits'] ?? null)) {
                throw new RuntimeException('Invalid adjustment record');
            }

            $code = $outputs[$file];

            if (hash('sha256', $code) !== $adjustment['input_sha256']) {
                throw new RuntimeException('Adjustment input hash mismatch: '.$file);
            }

            $lastOffset = strlen($code) + 1;

            foreach ($adjustment['edits'] as $edit) {
                if (! is_array($edit) || ! is_int($edit['offset'] ?? null) || ! is_string($edit['delete'] ?? null) || ! is_string($edit['insert'] ?? null) || $edit['offset'] < 0 || $lastOffset <= $edit['offset'] + strlen($edit['delete']) || substr($code, $edit['offset'], strlen($edit['delete'])) !== $edit['delete']) {
                    throw new RuntimeException('Invalid adjustment edit: '.$file);
                }

                $lastOffset = $edit['offset'];
                $code = substr_replace($code, $edit['insert'], $edit['offset'], strlen($edit['delete']));
            }

            if (hash('sha256', $code) !== $adjustment['output_sha256']) {
                throw new RuntimeException('Adjustment output hash mismatch: '.$file);
            }

            $outputs[$file] = $code;
        }

        $this->assertClosure($outputs, true);

        return $outputs;
    }

    /** @param array<string, string> $outputs */
    private function assertClosure(array $outputs, bool $strict): void
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;
        $classes = [];
        $references = [];

        foreach ($outputs as $path => $code) {
            if (! str_ends_with($path, '.php')) {
                continue;
            }

            if ($strict && preg_match('/@(?:phpstan|psalm)-ignore|@SuppressWarnings/', $code) === 1) {
                throw new RuntimeException('Static analysis suppression in native output: '.$path);
            }

            $nodes = $parser->parse($code);

            if ($nodes === null) {
                throw new RuntimeException('Cannot parse output: '.$path);
            }

            $nodes = new NodeTraverser(new NameResolver, new ParentConnectingVisitor)->traverse($nodes);

            foreach ($finder->findInstanceOf($nodes, ClassLike::class) as $class) {
                if ($class->name !== null && $class->namespacedName !== null) {
                    $classes[] = $class->namespacedName->toString();
                }
            }

            foreach ($finder->findInstanceOf($nodes, Name::class) as $name) {
                if ($name->getAttribute('parent') instanceof Namespace_) {
                    continue;
                }

                $reference = $name->toString();

                if (str_starts_with($reference, 'Symplify\\RuleDocGenerator\\') || str_starts_with($reference, 'RectorLaravel\\')) {
                    throw new RuntimeException('Obsolete dependency reference: '.$path);
                }

                if (str_starts_with($reference, 'CoreX\\Tooling\\Rector\\Laravel\\')) {
                    $references[] = $reference;
                }
            }
        }

        foreach ($references as $reference) {
            if (! in_array($reference, $classes, true)) {
                throw new RuntimeException('Missing native dependency closure: '.$reference);
            }
        }

        if (count($classes) !== 46) {
            throw new RuntimeException('Expected exactly 46 native production classes');
        }
    }
}
