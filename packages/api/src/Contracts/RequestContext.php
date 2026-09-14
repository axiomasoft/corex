<?php

declare(strict_types=1);

namespace CoreX\Api\Contracts;

use CoreX\Api\RequestMetadata;
use CoreX\Tenancy\TenantContext;

interface RequestContext
{
    public function tenant(): TenantContext;

    public function principal(): Principal;

    public function metadata(): RequestMetadata;
}
