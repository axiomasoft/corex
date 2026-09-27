<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs\Lifecycle;

use CoreX\Modules\Contracts\EnableHook;
use CoreX\Modules\ModuleRuntimeContext;

/** Idempotent (AC-7): claiming the same setting reference twice is a no-op update. */
final class StubEnableHook implements EnableHook
{
    public function __invoke(ModuleRuntimeContext $ctx): void
    {
        $ctx->records->claim($ctx->module, 'setting', 'greeting', ['value' => 'hello']);
    }
}
