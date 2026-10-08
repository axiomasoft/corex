<?php

declare(strict_types=1);

namespace CoreX\Access\Internal;

/*
 * Deprecated names of the principal concept, moved into the kernel
 * (CoreX\Context, audit 2026-10-08 P-02). Each old name is the SAME type as
 * its replacement (class_alias, not a subtype), so existing implementations
 * and type hints keep working. Loaded eagerly through composer autoload.files,
 * so it also works with an authoritative classmap. Removed in 2.0.
 */

use CoreX\Context\Contracts\Principal;
use CoreX\Context\Enums\PrincipalType;

foreach ([
    Principal::class => \CoreX\Access\Contracts\Principal::class,
    PrincipalType::class => \CoreX\Access\Enums\PrincipalType::class,
] as $kernelName => $deprecatedName) {
    if (! class_exists($deprecatedName, false) && ! interface_exists($deprecatedName, false) && ! enum_exists($deprecatedName, false)) {
        class_alias($kernelName, $deprecatedName);
    }
}
