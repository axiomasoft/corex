<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Bulk\Contracts\BulkDraftFactory;
use CoreX\Operations\Bulk\Contracts\BulkTargetResolver;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use Illuminate\Support\Str;

final readonly class BulkDrafts implements BulkDraftFactory, BulkTargetResolver
{
    public function __construct(private DataScopeCodec $scopes) {}

    public function operationId(RequestContext $context, JsonDocument $input): string
    {
        $this->logicalInput($input);

        return $this->equipment() ? 'equipment.bulk.transfer' : 'fixture.bulk';
    }

    public function create(OperationInvocation $invocation, DataScope $scope): BulkOperationDefinition
    {
        $input = $this->logicalInput($invocation->input);
        $operation = $invocation->operation;
        $client = $invocation->clientId;

        if ($client === null || $client === '') {
            throw new ApiError(errorCode: OperationsErrorCode::AuthenticationRequired, status: 401, message: 'An authenticated transfer client is required.');
        }

        return new BulkOperationDefinition(
            id: (string) Str::uuid7(),
            submissionId: 'candidate',
            operationId: $operation->id,
            major: $operation->version,
            contractHash: $operation->contractHash,
            encodedScope: $this->scopes->encode($scope),
            actorId: $scope->principalId,
            clientId: $client,
            extensionStamp: JsonDocuments::capture([
                'transfer_fingerprint' => InputIdentity::fingerprint($input),
                'owners' => $this->equipment() ? ['equipment/registry', 'axiomasoft/corex-import-export'] : [],
            ]),
            inputRef: JsonDocuments::capture($input),
            rowLimit: $input['row_limit'] ?? ($input['kind'] === 'import' ? 1 : 2),
            timeLimitSeconds: $input['time_limit_seconds'] ?? 4,
        );
    }

    private function logicalInput(JsonDocument $document): array
    {
        $input = JsonDocuments::map($document);

        if (! in_array($input['kind'] ?? null, ['import', 'export'], true)
            || ! in_array($input['source'] ?? null, ['equipment', 'equipment-alternate'], true)) {
            throw new ApiError(errorCode: OperationsErrorCode::ValidationFailed, status: 422, message: 'Unknown fixture transfer source or kind.');
        }

        return $input;
    }

    private function equipment(): bool
    {
        return getenv('COREX_SCENARIO_VARIANT') === 'equipment';
    }
}
