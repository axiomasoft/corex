<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use Illuminate\Http\Request;

/**
 * Determines the active tenant for a request (subdomain / header / DB strategy).
 */
interface TenantResolver
{
    public function resolve(Request $request): ?Tenant;
}
