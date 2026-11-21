<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Laravel;

use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Bulk\Contracts\BulkExecutor;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Contracts\BulkOwner;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Data\BulkOptions;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Data\OperationInvocation;
use CoreX\Operations\Laravel\ApiSchemaConnections;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class SubmitBulkOperation implements OperationHandler
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
    public function prepare(OperationInvocation $invocation, DataScope $scope, string $key, Connection $connection): void
    {
        $request = $this->app->make(Request::class);
        $draft = $request->attributes->get('corex.bulk_definition');
        $admitted = $request->attributes->get(OperationInvocation::class);

        if ($this->prepared !== null || $admitted !== $invocation || ! $draft instanceof BulkOperationDefinition
            || $connection !== $this->connections->tenant() || $connection->transactionLevel() < 1) {
            throw new ApiError(OperationsErrorCode::Forbidden, 403, 'Trusted bulk admission is required.');
        }

        if (! $this->app->bound(BulkExecutor::class) || ! $this->app->bound(BulkOwner::class)) {
            throw new ApiError(OperationsErrorCode::CapabilityUnavailable, 503, 'Bulk capability is unavailable.');
        }

        if ($this->secret === null || $this->secret === '') {
            throw new ApiError(OperationsErrorCode::CapabilityUnavailable, 503, 'Bulk admission is unavailable.');
        }

        $definition = new BulkOperationDefinition(
            $draft->id, hash_hmac('sha256', $key, $this->secret), $draft->operationId, $draft->major, $draft->contractHash,
            $draft->encodedScope, $draft->actorId, $draft->clientId, $draft->extensionStamp, $draft->inputRef,
            min($draft->rowLimit, $this->limits->rowLimit), min($draft->timeLimitSeconds, $this->limits->timeLimitSeconds),
        );
        $this->app->make(BulkOwner::class)->admit($definition->context($scope));
        $this->admission->enlist($scope, $connection, new ModuleRef('axiomasoft/corex-operations'), new ModuleRef($invocation->operation->module), ...BulkReferences::targets($definition));
        $this->prepared = $definition;
        $this->context = $invocation->context;
    }

    /** @internal Clear the per-invocation state on replay, rollback and commit. */
    public function clear(OperationInvocation $invocation): void
    {
        if ($this->context === $invocation->context) {
            $this->prepared = null;
            $this->context = null;
        }
    }

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        if ($this->prepared === null || $this->context !== $context || $this->connections->tenant()->transactionLevel() < 1) {
            throw new ApiError(OperationsErrorCode::Forbidden, 403, 'Trusted bulk admission is required.');
        }

        return ['operation_id' => $this->store->submit($this->prepared)->definition->id];
    }
}
