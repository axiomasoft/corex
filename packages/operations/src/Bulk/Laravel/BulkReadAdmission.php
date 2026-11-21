<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Data\Data\DataScope;
use CoreX\Operations\Authorization\Contracts\ClientScopeAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
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

    public function allows(DataScope $scope, BulkOperationDefinition $definition): bool
    {
        $request = $this->app->make(Request::class);
        $principal = $this->principals->resolve($request);

        if ($principal === null || $principal->principalId() !== $scope->principalId || $principal->principalType()->value !== $scope->principalKind) {
            return false;
        }

        foreach ($this->operations->all($definition->major) as $operation) {
            if ($operation->id !== $definition->operationId || $operation->handler !== SubmitBulkOperation::class
                || ! hash_equals($operation->contractHash, $definition->contractHash)) {
                continue;
            }

            $context = $this->contexts->resolve($request, $principal, InvocationMetadataFactory::fromRequest($request, $definition->major));

            return $this->modules->isActive($operation->module, $context->tenant())
                && $this->clients->allows($operation, $context) && $this->authorizer->allows($operation, $context);
        }

        return false;
    }
}
