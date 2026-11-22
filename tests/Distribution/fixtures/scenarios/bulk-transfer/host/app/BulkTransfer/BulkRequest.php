<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\Scenario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BulkRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('corex.client_id', getenv('COREX_BULK_CLIENT') ?: 'fixture-client');

        if ($request->isMethod('POST') && $request->is('api/v2/bulk-operations') && getenv('COREX_BULK_MODE') === 'submit-b') {
            Scenario::barrier('bulk-concurrent', Scenario::describe(app('db')->connection()));
        }

        return $next($request);
    }
}
