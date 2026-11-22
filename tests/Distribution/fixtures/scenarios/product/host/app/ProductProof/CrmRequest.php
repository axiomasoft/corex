<?php

declare(strict_types=1);

namespace App\ProductProof;

use App\Scenario\Scenario;
use App\Scenario\ScenarioQueue;
use Closure;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CrmRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $identity = match ($request->bearerToken()) {
            'fixture-crm-a' => [Scenario::ACCOUNT_A, Scenario::W1, Scenario::USER_A],
            'fixture-crm-a-other-workspace' => [Scenario::ACCOUNT_A, Scenario::W2, Scenario::USER_A],
            'fixture-crm-b' => [Scenario::ACCOUNT_B, Scenario::W2, Scenario::USER_B],
            default => null,
        };

        if ($identity === null) {
            return response()->json(['code' => 'host.authentication_required'], 401);
        }

        [$account, $workspace, $actor] = $identity;
        $request->attributes->set('corex.client_id', 'installed-crm-client');

        try {
            return ScenarioQueue::inScope($account, $workspace, $actor, static fn (): Response => $next($request));
        } catch (ScopeContextMismatch|ScopeStale) {
            return response()->json(['code' => 'host.scope_refused'], 403);
        }
    }
}
