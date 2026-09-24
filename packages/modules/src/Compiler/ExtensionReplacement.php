<?php

declare(strict_types=1);

namespace CoreX\Modules\Compiler;

/**
 * Explicit target→replacement directive for one extension point.
 *
 * @internal compiler input; not a runtime registry
 */
final readonly class ExtensionReplacement
{
    public function __construct(
        public string $point,
        public string $targetId,
        public string $replacementId,
    ) {}
}
