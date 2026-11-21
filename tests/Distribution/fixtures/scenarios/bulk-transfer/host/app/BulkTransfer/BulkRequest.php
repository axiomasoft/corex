<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\Scenario;
use Closure;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class BulkRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('corex.client_id', getenv('COREX_BULK_CLIENT') ?: 'fixture-client');

        if (! $request->isMethod('POST') || ! $request->is('api/v1/bulk-operations')) {
            return $next($request);
        }

        $kind = $request->input('kind');
        $source = $request->input('source');

        if (! in_array($kind, ['import', 'export'], true) || ! in_array($source, ['equipment', 'equipment-alternate'], true)) {
            return response()->json(['code' => 'invalid_fixture_input'], 422);
        }

        $scope = app(DataScopeFactory::class)->forCurrentPrincipal(app(TenantContextResolver::class)->current());
        $equipment = getenv('COREX_SCENARIO_VARIANT') === 'equipment';
        $operationId = $equipment ? 'equipment.bulk.transfer' : 'fixture.bulk';
        $operation = array_find(app(OperationRegistry::class)->all('v1'), static fn (Operation $operation): bool => $operation->id === $operationId);
        $input = ['kind' => $kind, 'source' => $source];
        $owners = $equipment ? ['equipment/registry', 'axiomasoft/corex-import-export'] : [];
        $request->attributes->set('corex.bulk_definition', new BulkOperationDefinition(
            (string) Str::uuid7(), 'candidate', $operation->id, 'v1', $operation->contractHash,
            app(DataScopeCodec::class)->encode($scope), $scope->principalId, $request->attributes->get('corex.client_id'),
            JsonDocuments::capture(['transfer_fingerprint' => InputIdentity::fingerprint($input), 'owners' => $owners]),
            JsonDocuments::capture($input), $kind === 'import' ? 1 : 2, 4,
        ));

        if (getenv('COREX_BULK_MODE') === 'submit-b') {
            Scenario::barrier('bulk-concurrent', Scenario::describe(app('db')->connection()));
        }

        return $next($request);
    }
}
