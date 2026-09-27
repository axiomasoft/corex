<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

/** Access for module-owned adapters invoked by an Action within the current operation. */
interface RequestContextProvider
{
    public function current(): RequestContext;
}
