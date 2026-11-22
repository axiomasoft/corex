<?php

declare(strict_types=1);

namespace App\TransportProof;

use App\Scenario\Scenario;
use App\Scenario\ScenarioQueue;
use Closure;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class TrustedRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $boxed = getenv('COREX_SCENARIO_VARIANT') === 'equipment';
        $first = $boxed ? Scenario::BOXED : Scenario::ACCOUNT_A;
        $identity = match ($request->bearerToken()) {
            'transport-alpha' => [$first, Scenario::W1, Scenario::USER_A, 'alpha-client'],
            'transport-manager' => [$first, null, Scenario::USER_A, 'webhook-client'],
            'transport-beta' => [$first, Scenario::W1, Scenario::USER_A, 'beta-client'],
            'transport-workspace' => [$first, Scenario::W2, Scenario::USER_A, 'alpha-client'],
            'transport-account' => $boxed ? null : [Scenario::ACCOUNT_B, Scenario::W2, Scenario::USER_B, 'alpha-client'],
            default => null,
        };

        if ($identity === null) {
            return response()->json(['code' => 'host.authentication_required'], 401);
        }
        [$account, $workspace, $actor, $client] = $identity;
        $request->attributes->set('corex.client_id', $client);

        try {
            return ScenarioQueue::inScope($account, $workspace, $actor, static function () use ($request, $next, $client, $account, $workspace, $actor): Response {
                $connection = DB::connection();
                $request->attributes->set('installed_scope_evidence', [
                    'client' => $client, 'account' => $account, 'workspace' => $workspace, 'actor' => $actor,
                    'connection' => Scenario::describe($connection),
                ]);

                if (getenv('COREX_SCENARIO_NOTES_OUTER') !== '1' || ! $request->is('api/v2/notes') || ! $request->isMethod('POST')) {
                    return $next($request);
                }

                return $connection->transaction(static function () use ($request, $next, $connection): Response {
                    $response = $next($request);
                    Scenario::barrier('notes-outer-open', [
                        'response' => json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR),
                        'status' => $response->getStatusCode(), 'transaction_level' => $connection->transactionLevel(),
                        'connection' => Scenario::describe($connection),
                    ]);

                    return $response;
                });
            });
        } catch (ScopeContextMismatch|ScopeStale) {
            return response()->json(['code' => 'host.scope_refused'], 403);
        }
    }
}
