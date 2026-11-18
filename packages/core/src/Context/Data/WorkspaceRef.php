<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use Packman\Attributes\Api;

/**
 * Immutable reference to a workspace: identity, slug, type, and its
 * position (path/parent) in the workspace hierarchy.
 *
 * Contract: B-10 §3.1, B-11 §3.1 (owner: B-11).
 */
#[Api(since: '1.0')]
final readonly class WorkspaceRef
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $type,
        public string $path,
        public ?string $parentId,
    ) {}
}
