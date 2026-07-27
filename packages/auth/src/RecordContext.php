<?php

declare(strict_types=1);

namespace CoreX\Auth;

/**
 * Contextual override key for the role matrix (pipeline/stage, B-11 §5.5) —
 * e.g. type "stage", id a stage uuid. AccessChecker::scopeFor() uses it to
 * look up context rows that replace the base scope for records in that
 * context, including narrowing it.
 *
 * @internal spec: B-11 §3.3
 */
final readonly class RecordContext
{
    public function __construct(
        public string $type,
        public string $id,
    ) {}
}
