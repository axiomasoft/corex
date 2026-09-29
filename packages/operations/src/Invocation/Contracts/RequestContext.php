<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Contracts;

use CoreX\Context\Data\TenantContext;
use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Invocation\InvocationMetadata;

interface RequestContext
{
    public function tenant(): TenantContext;

    public function principal(): Principal;

    public function metadata(): InvocationMetadata;
}
