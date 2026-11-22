<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;

final class TrustedClient
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->attributes->set('corex.client_id', 'client-1');

        return $next($request);
    }
}
