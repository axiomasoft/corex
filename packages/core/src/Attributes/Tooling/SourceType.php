<?php

declare(strict_types=1);

namespace CoreX\Attributes\Tooling;

use Packman\Attributes\Internal;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;

/** @internal Source declaration; never instantiated through package runtime boot. */
#[Internal]
final readonly class SourceType
{
    /** @param array<string, string> $imports */
    public function __construct(
        public string $name,
        public string $owner,
        public string $file,
        public bool $product,
        public ClassLike $node,
        public string $namespace,
        public array $imports,
    ) {}

    public function resolve(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        if (in_array(strtolower($name), ['self', 'static', '$this'], true)) {
            return $this->name;
        }

        if ($name === 'parent' && $this->node instanceof Class_) {
            return $this->node->extends?->toString() ?? 'parent';
        }
        $parts = explode('\\', $name, 2);

        if (strtolower($parts[0]) === 'namespace') {
            return trim($this->namespace.'\\'.($parts[1] ?? ''), '\\');
        }
        $import = $this->imports[strtolower($parts[0])] ?? null;

        return $import !== null ? $import.(isset($parts[1]) ? '\\'.$parts[1] : '') : trim($this->namespace.'\\'.$name, '\\');
    }
}
