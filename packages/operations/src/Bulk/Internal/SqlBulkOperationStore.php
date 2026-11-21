<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Data\BulkCheckpoint;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Data\BulkOwnerRef;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Bulk\Laravel\BulkReferences;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Override;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;
use RuntimeException;

/** @internal Tenant-local durable authority for bulk lifecycle state. */
#[Internal]
final readonly class SqlBulkOperationStore implements BulkOperationStore
{
    private const string Table = 'sys_api_bulk_operations';

    public function __construct(
        private Connection $connection,
        private DataScopeCodec $scopeCodec,
        private CurrentWorkScope $contexts,
        private ClockInterface $clock,
        private int $leaseSeconds = 30,
        private int $resultRetentionSeconds = 86_400,
        private ?ReferenceAdmission $admission = null,
    ) {
        if ($leaseSeconds < 1 || $resultRetentionSeconds < 1) {
            throw new InvalidArgumentException('Bulk lease and result retention must be positive.');
        }
    }

    #[Override]
    public function submit(BulkOperationDefinition $definition): BulkOperation
    {
        $scope = $this->definitionScope($definition);
        $this->assertScope($scope);

        return $this->connection->transaction(function () use ($scope, $definition): BulkOperation {
            $this->assertScope($scope);
            $this->enlist($scope, $definition);
            $now = $this->clock->now();
            $this->connection->table(self::Table)->insertOrIgnore([
                'id' => $definition->id,
                'major' => $definition->major,
                'operation_id' => $definition->operationId,
                'submission_id' => $definition->submissionId,
                'scope' => JsonCanonical::encode($definition->encodedScope),
                'logical_scope' => $this->logicalScope($scope),
                'row_limit' => $definition->rowLimit,
                'time_limit_seconds' => $definition->timeLimitSeconds,
                'recovery_attempts' => 0,
                'actor_id' => $definition->actorId,
                'client_id' => $definition->clientId,
                'contract_hash' => $definition->contractHash,
                'extension_stamp' => JsonCanonical::encode(JsonDocuments::map($definition->extensionStamp)),
                'input_ref' => JsonCanonical::encode(JsonDocuments::map($definition->inputRef)),
                'status' => BulkOperationStatus::Pending->value,
                'terminal_status' => null,
                'revision' => 0,
                'lease_owner' => null,
                'lease_until' => null,
                'cancel_requested_at' => null,
                'checkpoint' => '{}',
                'progress' => '{}',
                'result_ref' => null,
                'error' => null,
                'attempt' => 0,
                'next_attempt_at' => $now,
                'created_at' => $now,
                'finished_at' => null,
                'expires_at' => null,
            ]);

            $row = $this->connection->table(self::Table)
                ->where('logical_scope', $this->logicalScope($scope))
                ->where('client_id', $definition->clientId)
                ->where('major', $definition->major)
                ->where('operation_id', $definition->operationId)
                ->where('submission_id', $definition->submissionId)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new RuntimeException('Bulk submission could not be persisted.');
            }

            $stored = $this->operation($row);

            if (! $this->sameSubmission(requested: $definition, stored: $stored->definition)) {
                throw new RuntimeException('Bulk submission conflicts with the existing durable definition.');
            }

            return $stored;
        }, $this->transactionAttempts());
    }

    #[Override]
    public function find(DataScope $scope, string $id): ?BulkOperation
    {
        $this->assertScope($scope);
        $this->assertIdentifier(value: $id, name: 'operation id');

        $row = $this->connection->table(self::Table)->where('id', $id)->first();

        if ($row === null || ! $this->rowMatchesScope(row: $row, scope: $scope)) {
            return null;
        }

        return $this->operation($row);
    }

    #[Override]
    public function claim(DataScope $scope, string $worker): ?BulkOperation
    {
        $this->assertScope($scope);
        $this->assertIdentifier(value: $worker, name: 'worker');

        return $this->connection->transaction(function () use ($scope, $worker): ?BulkOperation {
            $this->assertScope($scope);
            $now = $this->clock->now();
            $query = $this->scopeQuery(query: $this->connection->table(self::Table), scope: $scope)
                ->where('next_attempt_at', '<=', $now)
                ->whereIn('status', [BulkOperationStatus::Pending->value, BulkOperationStatus::Running->value, BulkOperationStatus::Cancelling->value])
                ->where(static fn (Builder $query): Builder => $query->whereNull('lease_owner')->orWhere('lease_until', '<=', $now))
                ->oldest('next_attempt_at')->oldest()
                ->orderBy('id')
                ->limit(1);

            $this->lockClaimQuery($query);
            $row = $query->first();

            if ($row === null) {
                return null;
            }

            $operation = $this->operation($row);
            $this->enlist($scope, $operation->definition);
            $status = $operation->status;
            $nextStatus = $status === BulkOperationStatus::Pending
                ? BulkOperationStatus::Running
                : $status;
            $leaseUntil = $now->add(new DateInterval("PT{$this->leaseSeconds}S"));
            $changed = $this->connection->table(self::Table)
                ->where('id', DatabaseValue::text(DatabaseValue::field(row: $row, name: 'id')))
                ->where('status', $status->value)
                ->where('revision', DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'revision')))
                ->update([
                    'status' => $nextStatus->value,
                    'revision' => DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'revision')) + 1,
                    'lease_owner' => $worker,
                    'lease_until' => $leaseUntil,
                    'attempt' => $operation->attempt + 1,
                    'recovery_attempts' => $operation->recoveryAttempts + 1,
                ]);

            if ($changed !== 1) {
                throw new RuntimeException('Bulk claim lost its compare-and-swap fence.');
            }

            return $this->operation($this->requiredRow(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'id'))));
        }, $this->transactionAttempts());
    }

    #[Override]
    public function ownsClaim(DataScope $scope, BulkOperation $operation): bool
    {
        $this->assertScope($scope);

        return $operation->leaseOwner !== null && $this->scopeQuery($this->connection->table(self::Table), $scope)
            ->where('id', $operation->definition->id)
            ->where('lease_owner', $operation->leaseOwner)
            ->where('revision', $operation->revision)
            ->where('lease_until', '>', $this->clock->now())
            ->exists();
    }

    #[Override]
    public function save(BulkOperation $operation, string $leaseOwner, int $revision): bool
    {
        $scope = $this->definitionScope($operation->definition);
        $this->assertScope($scope);
        $this->assertIdentifier(value: $leaseOwner, name: 'lease owner');

        if ($revision < 0 || $operation->revision !== $revision || $operation->leaseOwner !== $leaseOwner) {
            throw new InvalidArgumentException('Bulk save must carry the claimed lease owner and revision.');
        }

        return $this->connection->transaction(function () use ($leaseOwner, $operation, $revision, $scope): bool {
            $this->assertScope($scope);
            $now = $this->clock->now();
            $row = $this->connection->table(self::Table)
                ->where('id', $operation->definition->id)
                ->where('lease_owner', $leaseOwner)
                ->where('revision', $revision)
                ->lockForUpdate()
                ->first();

            if ($row === null || ! $this->rowMatchesScope(row: $row, scope: $scope)) {
                return false;
            }

            $leaseUntil = $this->date(DatabaseValue::field(row: $row, name: 'lease_until'));

            if ($leaseUntil === null || $leaseUntil <= $now) {
                return false;
            }

            $stored = $this->operation($row);

            if (! $this->sameDefinition(stored: $stored->definition, requested: $operation->definition)) {
                throw new InvalidArgumentException('Bulk durable definition is immutable.');
            }

            $this->enlist($scope, $stored->definition);
            $this->assertTransition(from: $stored->status, to: $operation->status);
            $terminal = $operation->status->isTerminal();
            $changed = $this->connection->table(self::Table)
                ->where('id', $operation->definition->id)
                ->where('status', $stored->status->value)
                ->where('lease_owner', $leaseOwner)
                ->where('revision', $revision)
                ->where('lease_until', '>', $now)
                ->update([
                    'status' => $operation->status->value,
                    'terminal_status' => null,
                    'revision' => $revision + 1,
                    'lease_owner' => null,
                    'lease_until' => null,
                    'checkpoint' => JsonCanonical::encode($operation->checkpoint === null ? [] : [
                        'version' => 1,
                        'sequence' => $operation->checkpoint->sequence,
                        'owner_state' => JsonDocuments::map($operation->checkpoint->ownerState),
                    ]),
                    'owner_result_ref' => $this->ownerRefJson($operation->ownerResultRef),
                    'failure_page_ref' => $this->ownerRefJson($operation->failurePageRef),
                    'recovery_attempts' => $operation->recoveryAttempts,
                    'progress' => JsonCanonical::encode(JsonDocuments::map($operation->progress)),
                    'result_ref' => $this->jsonOrNull(JsonDocuments::optionalMap($operation->resultRef)),
                    'error' => $this->jsonOrNull(JsonDocuments::optionalMap($operation->error)),
                    'next_attempt_at' => $operation->nextAttemptAt,
                    'finished_at' => $terminal ? $now : null,
                    'expires_at' => $terminal
                        ? $now->add(new DateInterval("PT{$this->resultRetentionSeconds}S"))
                        : null,
                ]);

            return $changed === 1;
        }, $this->transactionAttempts());
    }

    #[Override]
    public function cancel(DataScope $scope, string $id): BulkOperation
    {
        $this->assertScope($scope);
        $this->assertIdentifier(value: $id, name: 'operation id');

        return $this->connection->transaction(function () use ($id, $scope): BulkOperation {
            $this->assertScope($scope);
            $row = $this->connection->table(self::Table)->where('id', $id)->lockForUpdate()->first();

            if ($row === null || ! $this->rowMatchesScope(row: $row, scope: $scope)) {
                throw new RuntimeException('Bulk operation was not found in the active scope.');
            }

            $operation = $this->operation($row);

            if ($operation->status->isTerminal() || $operation->status === BulkOperationStatus::Cancelling) {
                return $operation;
            }

            $this->enlist($scope, $operation->definition);
            $now = $this->clock->now();
            $nextStatus = $operation->status === BulkOperationStatus::Pending
                ? BulkOperationStatus::Cancelled
                : BulkOperationStatus::Cancelling;
            $terminal = $nextStatus === BulkOperationStatus::Cancelled;
            $changed = $this->connection->table(self::Table)
                ->where('id', $id)
                ->where('status', $operation->status->value)
                ->where('revision', $operation->revision)
                ->update([
                    'status' => $nextStatus->value,
                    'revision' => $operation->revision + 1,
                    'lease_owner' => $terminal ? null : $operation->leaseOwner,
                    'lease_until' => $terminal ? null : $operation->leaseUntil,
                    'cancel_requested_at' => $operation->cancelRequestedAt ?? $now,
                    'finished_at' => $terminal ? $now : null,
                    'expires_at' => $terminal
                        ? $now->add(new DateInterval("PT{$this->resultRetentionSeconds}S"))
                        : null,
                ]);

            if ($changed !== 1) {
                throw new RuntimeException('Bulk cancellation lost its compare-and-swap fence.');
            }

            return $this->operation($this->requiredRow($id));
        }, $this->transactionAttempts());
    }

    private function definitionScope(BulkOperationDefinition $definition): DataScope
    {
        $scope = $this->scopeCodec->decode($definition->encodedScope);

        if ($this->scopeCodec->encode($scope) !== $definition->encodedScope) {
            throw new InvalidArgumentException('Bulk encoded scope is not canonical.');
        }

        if ($scope->principalId !== $definition->actorId) {
            throw new InvalidArgumentException('Bulk actor does not match the durable scope.');
        }

        return $scope;
    }

    private function assertScope(DataScope $scope): void
    {
        $this->contexts->assert($scope, $this->connection);
    }

    private function scopeQuery(Builder $query, DataScope $scope): Builder
    {
        $query->where('logical_scope', $this->logicalScope($scope));
        $encoded = JsonCanonical::encode($this->scopeCodec->encode($scope));

        if ($this->connection->getDriverName() === 'pgsql') {
            return $query->whereRaw('scope::jsonb = ?::jsonb', [$encoded]);
        }

        return $query->where('scope', $encoded);
    }

    private function lockClaimQuery(Builder $query): void
    {
        if ($this->connection->getDriverName() === 'pgsql') {
            $query->lock('for update skip locked');

            return;
        }

        $query->lockForUpdate();
    }

    private function rowMatchesScope(object $row, DataScope $scope): bool
    {
        return hash_equals(
            JsonCanonical::encode($this->scopeCodec->encode($scope)),
            JsonCanonical::encode($this->scopeArray(DatabaseValue::field(row: $row, name: 'scope'))),
        );
    }

    private function sameSubmission(BulkOperationDefinition $requested, BulkOperationDefinition $stored): bool
    {
        return $requested->submissionId === $stored->submissionId
            && $requested->operationId === $stored->operationId
            && $requested->major === $stored->major
            && $requested->rowLimit === $stored->rowLimit
            && $requested->timeLimitSeconds === $stored->timeLimitSeconds
            && $requested->contractHash === $stored->contractHash
            && JsonCanonical::encode($requested->encodedScope) === JsonCanonical::encode($stored->encodedScope)
            && $requested->actorId === $stored->actorId
            && $requested->clientId === $stored->clientId
            && JsonCanonical::encode(JsonDocuments::map($requested->extensionStamp)) === JsonCanonical::encode(JsonDocuments::map($stored->extensionStamp))
            && JsonCanonical::encode(JsonDocuments::map($requested->inputRef)) === JsonCanonical::encode(JsonDocuments::map($stored->inputRef));
    }

    private function sameDefinition(BulkOperationDefinition $stored, BulkOperationDefinition $requested): bool
    {
        return $stored->id === $requested->id && $this->sameSubmission(requested: $requested, stored: $stored);
    }

    private function assertTransition(BulkOperationStatus $from, BulkOperationStatus $to): void
    {
        $allowed = match ($from) {
            BulkOperationStatus::Running => [
                BulkOperationStatus::Running,
                BulkOperationStatus::Completed,
                BulkOperationStatus::Failed,
            ],
            BulkOperationStatus::Cancelling => [
                BulkOperationStatus::Cancelling,
                BulkOperationStatus::Cancelled,
                BulkOperationStatus::Completed,
                BulkOperationStatus::Failed,
            ],
            default => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidArgumentException("Invalid bulk transition [{$from->value} -> {$to->value}].");
        }
    }

    private function requiredRow(string $id): object
    {
        return $this->connection->table(self::Table)->where('id', $id)->first()
            ?? throw new RuntimeException('Bulk operation disappeared during a locked transition.');
    }

    private function operation(object $row): BulkOperation
    {
        $definition = new BulkOperationDefinition(
            id: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'id')),
            submissionId: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'submission_id')),
            operationId: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'operation_id')),
            major: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'major')),
            contractHash: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'contract_hash')),
            encodedScope: $this->canonicalScopeArray(DatabaseValue::field(row: $row, name: 'scope')),
            actorId: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'actor_id')),
            clientId: DatabaseValue::text(DatabaseValue::field(row: $row, name: 'client_id')),
            extensionStamp: JsonDocuments::capture($this->objectArray(value: DatabaseValue::field(row: $row, name: 'extension_stamp'), name: 'extension stamp')),
            inputRef: JsonDocuments::capture($this->inputReference($row)),
            rowLimit: DatabaseValue::integer(DatabaseValue::field($row, 'row_limit')),
            timeLimitSeconds: DatabaseValue::integer(DatabaseValue::field($row, 'time_limit_seconds')),
        );

        return new BulkOperation(
            definition: $definition,
            status: BulkOperationStatus::from(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'status'))),
            terminalStatus: DatabaseValue::field(row: $row, name: 'terminal_status') === null
                ? null
                : BulkOperationStatus::from(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'terminal_status'))),
            revision: DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'revision')),
            leaseOwner: DatabaseValue::field(row: $row, name: 'lease_owner') === null ? null : DatabaseValue::text(DatabaseValue::field(row: $row, name: 'lease_owner')),
            leaseUntil: $this->date(DatabaseValue::field(row: $row, name: 'lease_until')),
            cancelRequestedAt: $this->date(DatabaseValue::field(row: $row, name: 'cancel_requested_at')),
            checkpoint: $this->checkpoint(DatabaseValue::field($row, 'checkpoint')),
            progress: JsonDocuments::capture($this->objectArray(value: DatabaseValue::field(row: $row, name: 'progress'), name: 'progress')),
            resultRef: JsonDocuments::nullable($this->nullableObjectArray(value: DatabaseValue::field(row: $row, name: 'result_ref'), name: 'result reference')),
            error: JsonDocuments::nullable($this->nullableObjectArray(value: DatabaseValue::field(row: $row, name: 'error'), name: 'error')),
            attempt: DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'attempt')),
            nextAttemptAt: $this->requiredDate(value: DatabaseValue::field(row: $row, name: 'next_attempt_at'), name: 'next attempt'),
            createdAt: $this->requiredDate(value: DatabaseValue::field(row: $row, name: 'created_at'), name: 'created'),
            finishedAt: $this->date(DatabaseValue::field(row: $row, name: 'finished_at')),
            expiresAt: $this->date(DatabaseValue::field(row: $row, name: 'expires_at')),
            ownerResultRef: $this->ownerRef(DatabaseValue::field($row, 'owner_result_ref')),
            failurePageRef: $this->ownerRef(DatabaseValue::field($row, 'failure_page_ref')),
            recoveryAttempts: DatabaseValue::integer(DatabaseValue::field($row, 'recovery_attempts')),
        );
    }

    /** @return array<array-key, mixed> */
    private function inputReference(object $row): array
    {
        $input = $this->objectArray(DatabaseValue::field($row, 'input_ref'), 'input reference');

        if ($input === [] && DatabaseValue::field($row, 'finished_at') !== null) {
            return ['kind' => 'expired'];
        }

        return $input;
    }

    private function enlist(DataScope $scope, BulkOperationDefinition $definition): void
    {
        $this->admission?->enlist($scope, $this->connection, new ModuleRef('axiomasoft/corex-operations'), ...BulkReferences::targets($definition));
    }

    private function logicalScope(DataScope $scope): string
    {
        return hash('sha256', JsonCanonical::encode([$scope->accountId, $scope->accountIncarnation, $scope->workspaceId, $scope->principalKind, $scope->principalId]));
    }

    private function checkpoint(mixed $value): ?BulkCheckpoint
    {
        $data = $this->objectArray($value, 'checkpoint');

        if ($data === []) {
            return null;
        }

        if (($data['version'] ?? null) !== 1 || ! is_int($data['sequence'] ?? null) || ! is_array($data['owner_state'] ?? null)) {
            throw new RuntimeException('Bulk checkpoint envelope is invalid.');
        }

        return new BulkCheckpoint($data['sequence'], JsonDocuments::capture($data['owner_state']));
    }

    private function ownerRef(mixed $value): ?BulkOwnerRef
    {
        $data = $this->nullableObjectArray($value, 'owner reference');

        if ($data === null) {
            return null;
        }

        if (($data['version'] ?? null) !== 1 || ! is_string($data['owner'] ?? null) || ! is_string($data['kind'] ?? null) || ! is_string($data['id'] ?? null)) {
            throw new RuntimeException('Bulk owner reference envelope is invalid.');
        }

        return new BulkOwnerRef($data['owner'], $data['kind'], $data['id']);
    }

    private function ownerRefJson(?BulkOwnerRef $ref): ?string
    {
        return $ref === null ? null : JsonCanonical::encode(['version' => 1, 'owner' => $ref->owner, 'kind' => $ref->kind, 'id' => $ref->id]);
    }

    /** @return array<string, scalar|null> */
    private function scopeArray(mixed $value): array
    {
        $decoded = $this->decodedArray(value: $value, name: 'scope');

        $scope = [];
        foreach ($decoded as $key => $item) {
            if (! is_string($key) || ! is_scalar($item) && $item !== null) {
                throw new RuntimeException('Bulk scope contains a non-scalar value.');
            }
            $scope[$key] = $item;
        }

        return $scope;
    }

    /** @return array<string, scalar|null> */
    private function canonicalScopeArray(mixed $value): array
    {
        return $this->scopeCodec->encode($this->scopeCodec->decode($this->scopeArray($value)));
    }

    /** @return array<array-key, mixed> */
    private function objectArray(mixed $value, string $name): array
    {
        $decoded = $this->decodedArray(value: $value, name: $name);

        if ($decoded !== [] && array_is_list($decoded)) {
            throw new RuntimeException("Bulk {$name} must be a JSON object.");
        }

        return $decoded;
    }

    /** @return array<array-key, mixed>|null */
    private function nullableObjectArray(mixed $value, string $name): ?array
    {
        return $value === null ? null : $this->objectArray(value: $value, name: $name);
    }

    /** @return array<array-key, mixed> */
    private function decodedArray(mixed $value, string $name): array
    {
        $decoded = is_string($value) ? JsonCanonical::decode($value) : $value;

        if (! is_array($decoded)) {
            throw new RuntimeException("Bulk {$name} is not a JSON object.");
        }

        return $decoded;
    }

    /** @param array<string, mixed>|null $value */
    private function jsonOrNull(?array $value): ?string
    {
        return $value === null ? null : JsonCanonical::encode($value);
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($value)
            : new DateTimeImmutable(DatabaseValue::text($value));
    }

    private function requiredDate(mixed $value, string $name): DateTimeImmutable
    {
        return $this->date($value) ?? throw new RuntimeException("Bulk {$name} timestamp is missing.");
    }

    private function assertIdentifier(string $value, string $name): void
    {
        if ($value === '' || strlen($value) > 64 || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
            throw new InvalidArgumentException("Bulk {$name} must contain 1-64 safe bytes.");
        }
    }

    /** @return int<1, 3> */
    private function transactionAttempts(): int
    {
        return $this->connection->getDriverName() === 'pgsql' ? 3 : 1;
    }
}
