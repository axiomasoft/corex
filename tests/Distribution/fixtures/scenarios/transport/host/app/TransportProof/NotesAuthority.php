<?php

declare(strict_types=1);

namespace App\TransportProof;

use CoreX\Context\Contracts\Principal;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationFieldAuthorizer;
use CoreX\Operations\Authorization\Contracts\OperationResourceAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Idempotency\Data\StoredOperationOutcome;
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Validation\Data\JsonObject;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolDescriptor;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;

final readonly class NotesAuthority implements OperationAuthorizer, OperationFieldAuthorizer, OperationResourceAuthorizer, PrincipalResolver, ToolPolicy
{
    public function __construct(private DatabaseManager $database) {}

    public function resolve(Request $request): ?Principal
    {
        $principal = $request->user();

        return $principal instanceof Principal ? $principal : null;
    }

    public function allows(Operation $operation, RequestContext $context): bool
    {
        return in_array($operation->module, [NotesManifest::OWNER, 'equipment/registry'], true) && $this->grant($context, $operation->ability);
    }

    public function clientAllows(Operation $operation, RequestContext $context): bool
    {
        return $this->grant($context, 'client') && array_all($operation->requiredScopes, fn (string $scope): bool => $this->grant($context, $scope));
    }

    public function allowsRead(Operation $operation, RequestContext $context, string $path): bool
    {
        return $operation->id !== 'notes.create' || $path !== '/private_label' || $this->grant($context, 'read-private-label');
    }

    public function allowsWrite(Operation $operation, RequestContext $context, string $path): bool
    {
        return $operation->id !== 'notes.create' || $path !== '/title' || $this->grant($context, 'write-title');
    }

    public function authorize(OperationInvocation $invocation, ?StoredOperationOutcome $outcome = null): void
    {
        if ($invocation->operation->id !== 'notes.create') {
            return;
        }
        $context = $invocation->context;
        $record = JsonDocuments::map($invocation->input)['resource_id'] ?? null;

        if (! is_string($record) || ! $this->grant($context, 'record:'.$record)
            || ! $this->database->connection()->table('installed_notes_subjects')->where([
                'workspace_id' => $context->tenant()->workspace?->id ?? 'account', 'id' => $record,
            ])->exists()) {
            throw new ApiError(errorCode: OperationsErrorCode::NotFound, status: 404, message: 'Resource is unavailable.');
        }
    }

    public function discover(ToolDescriptor $tool, RequestContext $context): bool
    {
        return $this->grant($context, 'client') && match ($tool->code) {
            'notes.create' => $this->grant($context, 'notes.write'),
            'equipment.transfer' => $this->grant($context, 'equipment.bulk.transfer'),
            default => false,
        };
    }

    public function invoke(ToolDescriptor $tool, RequestContext $context, JsonObject $arguments): bool
    {
        return $this->discover($tool, $context);
    }

    private function grant(RequestContext $context, string $grant): bool
    {
        return $context instanceof ClientRequestContext && $this->database->connection()->table('installed_transport_grants')->where([
            'workspace_id' => $context->tenant()->workspace?->id ?? 'account',
            'principal_id' => $context->principal()->principalId(), 'client_id' => $context->clientId(), 'grant' => $grant,
        ])->exists();
    }
}
