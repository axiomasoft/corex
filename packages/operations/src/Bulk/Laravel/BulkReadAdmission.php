<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Data\Data\DataScope;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Internal\AuthenticatedRequestContext;
use CoreX\Operations\Invocation\Laravel\AuthenticatedOperationContext;
use CoreX\Operations\Invocation\Laravel\InvocationMetadataFactory;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Registry\ModuleRegistry;

#[Internal]
final readonly class BulkReadAdmission
{
    public function __construct(
        private Application $app,
        private OperationRegistry $operations,
        private PrincipalResolver $principals,
        private ContextResolver $contexts,
        private ModuleRegistry $modules,
        private ClientScopeAuthorizer $clients,
        private OperationAuthorizer $authorizer,
    ) {}

    public function allows(DataScope $scope, BulkOperationDefinition $definition, ?RequestContext $context = null): bool
    {
        $request = $this->app->make(Request::class);
        $current = $request->attributes->get(RequestContext::class);
        $context ??= $current instanceof ClientRequestContext ? $current : null;
        $principal = $context?->principal() ?? $this->principals->resolve($request);

        if ($principal === null || $principal->principalId() !== $scope->principalId || $principal->principalType()->value !== $scope->principalKind) {
            return false;
        }

        foreach ($this->operations->all($definition->major) as $operation) {
            if ($operation->id !== $definition->operationId || $operation->handler !== SubmitBulkOperation::class
                || ! hash_equals($operation->contractHash, $definition->contractHash)) {
                continue;
            }

            $metadata = InvocationMetadataFactory::fromRequest(request: $request, version: $definition->major);
            $context ??= $this->contexts->resolve(request: $request, principal: $principal, metadata: $metadata);
            $client = $context instanceof ClientRequestContext ? $context->clientId() : $request->attributes->get('corex.client_id');

            if (! is_string($client) || ! hash_equals($definition->clientId, $client)) {
                return false;
            }
            $context = new AuthenticatedRequestContext(context: $context, client: $client);

            return $this->allowed(context: $context, operation: $operation);
        }

        return false;
    }

    public function allowsStatus(RequestContext $context): bool
    {
        if (! $context instanceof ClientRequestContext || ! $this->app->make('router')->has('corex.api.bulk.show')) {
            return false;
        }

        $show = array_find($this->operations->all(version: 'v2'), static fn (Operation $operation): bool => $operation->id === 'bulk-operations.show' && $operation->handler === BulkManagementOperation::class);

        return $show !== null && $this->allowed(context: $context, operation: $show);
    }

    private function allowed(ClientRequestContext $context, Operation $operation): bool
    {
        $context = AuthenticatedOperationContext::forOperation(context: $context, operation: $operation);

        return $operation->executionSupported && $this->modules->isActive(name: $operation->module, ctx: $context->tenant())
            && $this->clients->allows(operation: $operation, context: $context)
            && $this->authorizer->allows(operation: $operation, context: $context);
    }
}
