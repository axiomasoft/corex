<?php

declare(strict_types=1);

namespace App\ProductProof;

use CoreX\Context\Contracts\Principal;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;

final readonly class CrmPolicy implements OperationAuthorizer, OperationFieldAuthorizer, PrincipalResolver
{
    public function __construct(private DatabaseManager $database) {}

    public function resolve(Request $request): ?Principal
    {
        $principal = $request->user();

        return $principal instanceof Principal ? $principal : null;
    }

    public function allows(Operation $operation, RequestContext $context): bool
    {
        return $operation->module === 'flex/crm' && $this->grant($context, $operation->ability, 'action');
    }

    public function allowsRead(Operation $operation, RequestContext $context, string $path): bool
    {
        return $this->allows($operation, $context);
    }

    public function allowsWrite(Operation $operation, RequestContext $context, string $path): bool
    {
        return $this->allows($operation, $context)
            && ($operation->id !== 'crm.company.update' || $this->grant($context, $operation->ability, $path));
    }

    private function grant(RequestContext $context, string $ability, string $field): bool
    {
        return $this->database->connection()->table('installed_crm_grants')->where([
            'workspace_id' => $context->tenant()->workspace?->id,
            'principal_id' => $context->principal()->principalId(),
            'ability' => $ability,
            'field' => $field,
        ])->exists();
    }
}
