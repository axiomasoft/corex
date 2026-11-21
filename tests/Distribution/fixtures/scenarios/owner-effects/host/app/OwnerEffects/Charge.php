<?php

declare(strict_types=1);

namespace App\OwnerEffects;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Definition\Data\Operation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class Charge implements OperationAuthorizer, OperationHandler
{
    public function allows(Operation $operation, RequestContext $context): bool
    {
        $scope = Effects::scope();

        return $scope->principalId === $context->principal()->principalId() && $operation->id === 'fixture.media.charge'
            && $operation->module === Effects::MEDIA && DB::table('scenario_operation_grants')->where('scope', Effects::identity($scope))->exists();
    }

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        if (DB::connection()->transactionLevel() < 1) {
            throw new RuntimeException('Owner charge requires the real idempotency transaction.');
        }
        DB::table('scenario_charged_effects')->insert(['effect' => 'charge-thumbnail', 'scope' => Effects::identity(Effects::scope())]);

        return ['reference' => 'charge-thumbnail'];
    }
}
