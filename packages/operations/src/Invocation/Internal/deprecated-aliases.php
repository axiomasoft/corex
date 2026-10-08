<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

/*
 * Deprecated names of the operation-handler contract, moved into the kernel
 * (CoreX\Invocation, audit 2026-10-08 P-02). Each old name is the SAME type
 * as its replacement (class_alias, not a subtype), so existing handlers keep
 * their `handle(RequestContext ...)` signatures. The operations Principal now
 * names the single kernel principal (CoreX\Context\Contracts\Principal):
 * callers use principalId() instead of the former id(). Loaded eagerly through
 * composer autoload.files, so it also works with an authoritative classmap.
 * Removed in 2.0.
 */

use CoreX\Context\Contracts\Principal;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;

foreach ([
    OperationHandler::class => \CoreX\Operations\Definition\Contracts\OperationHandler::class,
    RequestContext::class => \CoreX\Operations\Invocation\Contracts\RequestContext::class,
    Principal::class => \CoreX\Operations\Authorization\Contracts\Principal::class,
    InvocationMetadata::class => \CoreX\Operations\Invocation\Data\InvocationMetadata::class,
] as $kernelName => $deprecatedName) {
    if (! class_exists($deprecatedName, false) && ! interface_exists($deprecatedName, false)) {
        class_alias($kernelName, $deprecatedName);
    }
}
