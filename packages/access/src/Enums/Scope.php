<?php

declare(strict_types=1);

namespace CoreX\Access\Enums;

use CoreX\Attributes\Api;

/**
 * Row-visibility scope compiled from the role matrix (B-11 §5.5). Several
 * roles resolve to the max weight of their base rows; a contextual row
 * (pipeline/stage) then overrides the base row for its own context,
 * including narrowing it.
 */
#[Api(since: '1.0')]
enum Scope: string
{
    case None = 'none';
    case Own = 'own';
    case Dept = 'dept';
    case DeptTree = 'dept_tree';
    case Workspace = 'workspace';
    case Account = 'account';

    public function weight(): int
    {
        return match ($this) {
            self::None => 0,
            self::Own => 1,
            self::Dept => 2,
            self::DeptTree => 3,
            self::Workspace => 4,
            self::Account => 5,
        };
    }
}
