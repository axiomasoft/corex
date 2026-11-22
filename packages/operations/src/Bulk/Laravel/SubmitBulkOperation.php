<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Exceptions\ScopeStale;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Bulk\Contracts\BulkDraftFactory;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Contracts\BulkOwner;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Data\BulkOptions;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\OperationAcceptance;
use CoreX\Operations\Invocation\Data\AcceptedOperation;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Database\Connection;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class SubmitBulkOperation implements OperationAcceptance, OperationHandler
{
    private ?BulkOperationDefinition $prepared = null;

    private ?RequestContext $context = null;

    public function __construct(
        private readonly Application $app,
        private readonly BulkOperationStore $store,
        private readonly ApiSchemaConnections $connections,
        private readonly BulkOptions $limits,
        private readonly ?string $secret,
        private readonly ReferenceAdmission $admission,
    ) {}

    /** @internal Recognized by the tenant-transactional invoker before idempotency lookup. */
    public function prepare(OperationInvocation $invocation, DataScope $scope, string $key, Connection $connection): BulkOperationDefinition
    {
        if ($this->prepared !== null || $connection !== $this->connections->tenant() || $connection->transactionLevel() < 1) {
            throw new ApiError(errorCode: OperationsErrorCode::Forbidden, status: 403, message: 'Trusted bulk admission is required.');
        }

        if (! $this->app->bound(BulkExecutor::class) || ! $this->app->bound(BulkOwner::class)
            || ! $this->app->bound(BulkDraftFactory::class) || $this->secret === null || $this->secret === '') {
            throw new ApiError(errorCode: OperationsErrorCode::CapabilityUnavailable, status: 503, message: 'Bulk capability is unavailable.');
        }

        if (! $this->app->make(BulkReadAdmission::class)->allowsStatus(context: $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'Bulk status is not authorized.');
        }
        $draft = $this->app->make(BulkDraftFactory::class)->create(invocation: $invocation, scope: $scope);
        $encoded = $this->app->make(DataScopeCodec::class)->encode($scope);

        if ($draft->actorId !== $scope->principalId || $draft->clientId !== $invocation->clientId
            || $draft->encodedScope !== $encoded || $draft->operationId !== $invocation->operation->id
            || $draft->major !== 'v2' || ! hash_equals($draft->contractHash, $invocation->operation->contractHash)) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'Invalid bulk admission scope.');
        }

        $definition = new BulkOperationDefinition(
            id: $draft->id, submissionId: hash_hmac('sha256', $key, $this->secret), operationId: $draft->operationId, major: $draft->major, contractHash: $draft->contractHash,
            encodedScope: $draft->encodedScope, actorId: $draft->actorId, clientId: $draft->clientId, extensionStamp: $draft->extensionStamp, inputRef: $draft->inputRef,
            rowLimit: min($draft->rowLimit, $this->limits->rowLimit), timeLimitSeconds: min($draft->timeLimitSeconds, $this->limits->timeLimitSeconds),
        );
        $this->app->make(BulkOwner::class)->admit($definition->context($scope));
        $this->admission->enlist($scope, $connection, new ModuleRef('axiomasoft/corex-operations'), new ModuleRef($invocation->operation->module), ...BulkReferences::targets($definition));
        $this->prepared = $definition;
        $this->context = $invocation->context;

        return $definition;
    }

    /** @internal Clear the per-invocation state on replay, rollback and commit. */
    public function clear(OperationInvocation $invocation): void
    {
        if ($this->context === $invocation->context) {
            $this->prepared = null;
            $this->context = null;
        }
    }

    public function acceptance(RequestContext $context, array $result): ?AcceptedOperation
    {
        $id = $result['operation_id'] ?? null;

        if (! is_string($id)) {
            return null;
        }

        $url = $this->app->make(UrlGenerator::class)->route('corex.api.bulk.show', ['id' => $id], false);

        return new AcceptedOperation(operationRef: $id, statusUri: $url);
    }

    /** Reauthorize the current stored target before releasing an accepted receipt. */
    public function release(OperationInvocation $invocation, AcceptedOperation $accepted): AcceptedOperation
    {
        $scope = $this->app->make(DataScopeFactory::class)->forCurrentPrincipal($invocation->context->tenant());

        try {
            $owned = $this->store->find(scope: $scope, id: $accepted->operationRef);
        } catch (ScopeContextMismatch|ScopeStale) {
            $owned = null;
        }

        if ($owned === null || $owned->definition->clientId !== $invocation->clientId
            || ! $this->app->make(BulkReadAdmission::class)->allowsStatus(context: $invocation->context)
            || ! $this->app->make(BulkReadAdmission::class)->allows(scope: $scope, definition: $owned->definition, context: $invocation->context)) {
            throw new ApiError(errorCode: OperationsErrorCode::NotFound, status: 404, message: 'Bulk operation is unavailable.');
        }

        return $this->acceptance(context: $invocation->context, result: ['operation_id' => $accepted->operationRef])
            ?? throw new ApiError(errorCode: OperationsErrorCode::NotFound, status: 404, message: 'Bulk status is unavailable.');
    }

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        if ($this->prepared === null || $this->context !== $context || $this->connections->tenant()->transactionLevel() < 1) {
            throw new ApiError(errorCode: OperationsErrorCode::Forbidden, status: 403, message: 'Trusted bulk admission is required.');
        }

        return ['operation_id' => $this->store->submit($this->prepared)->definition->id];
    }
}
