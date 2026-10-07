<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use CoreX\Context\Contracts\TenantContextRestorer;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Bulk\Contracts\BulkOperationStore;
use CoreX\Operations\Bulk\Data\BulkOperation;
use CoreX\Operations\Bulk\Data\BulkOperationDefinition;
use CoreX\Operations\Bulk\Enums\BulkOperationStatus;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Override;
use Psr\Clock\ClockInterface;
use RuntimeException;

/** @internal Tenant-local durable authority for bulk lifecycle state. */
final readonly class SqlBulkOperationStore implements BulkOperationStore
{
    private const string Table = 'sys_api_bulk_operations';

    public function __construct(
        private Connection $connection,
        private DataScopeCodec $scopeCodec,
        private TenantContextRestorer $contexts,
        private ClockInterface $clock,
        private int $leaseSeconds = 30,
        private int $resultRetentionSeconds = 86_400,
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

        return $this->connection->transaction(function () use ($definition): BulkOperation {
            $now = $this->clock->now();
            $this->connection->table(self::Table)->insertOrIgnore([
                'id' => $definition->id,
                'major' => $definition->major,
                'operation_id' => $definition->operationId,
                'submission_id' => $definition->submissionId,
                'scope' => JsonCanonical::encode($definition->encodedScope),
                'actor_id' => $definition->actorId,
                'client_id' => $definition->clientId,
                'contract_hash' => $definition->contractHash,
                'extension_stamp' => JsonCanonical::encode(JsonDocuments::map($definition->extensionStamp)),
                'input_ref' => JsonCanonical::encode(JsonDocuments::map($definition->inputRef)),
                'transfer_ref' => null,
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
            $now = $this->clock->now();
            $query = $this->scopeQuery(query: $this->connection->table(self::Table), scope: $scope)
                ->where(function (Builder $query) use ($now): void {
                    $query->where(function (Builder $pending) use ($now): void {
                        $pending->where('status', BulkOperationStatus::Pending->value)
                            ->where('next_attempt_at', '<=', $now)
                            ->whereNull('lease_owner');
                    })->orWhere(function (Builder $recoverable) use ($now): void {
                        $recoverable->whereIn('status', [
                            BulkOperationStatus::Running->value,
                            BulkOperationStatus::Cancelling->value,
                        ])->where('lease_until', '<=', $now);
                    });
                })
                ->oldest('next_attempt_at')->oldest()
                ->orderBy('id')
                ->limit(1);

            $this->lockClaimQuery($query);
            $row = $query->first();

            if ($row === null) {
                return null;
            }

            $status = BulkOperationStatus::from(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'status')));
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
                    'attempt' => DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'attempt')) + 1,
                ]);

            if ($changed !== 1) {
                throw new RuntimeException('Bulk claim lost its compare-and-swap fence.');
            }

            return $this->operation($this->requiredRow(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'id'))));
        }, $this->transactionAttempts());
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

            $this->assertTransition(from: $stored->status, to: $operation->status);
            $terminal = $operation->status->isTerminal();
            $changed = $this->connection->table(self::Table)
                ->where('id', $operation->definition->id)
                ->where('status', $stored->status->value)
                ->where('lease_owner', $leaseOwner)
                ->where('revision', $revision)
                ->where('lease_until', '>', $now)
                ->update([
                    'transfer_ref' => $this->jsonOrNull(JsonDocuments::optionalMap($operation->transferRef)),
                    'status' => $operation->status->value,
                    'terminal_status' => null,
                    'revision' => $revision + 1,
                    'lease_owner' => $terminal ? null : $leaseOwner,
                    'lease_until' => $terminal ? null : $now->add(new DateInterval("PT{$this->leaseSeconds}S")),
                    'checkpoint' => JsonCanonical::encode(JsonDocuments::map($operation->checkpoint)),
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
            $row = $this->connection->table(self::Table)->where('id', $id)->lockForUpdate()->first();

            if ($row === null || ! $this->rowMatchesScope(row: $row, scope: $scope)) {
                throw new RuntimeException('Bulk operation was not found in the active scope.');
            }

            $operation = $this->operation($row);

            if ($operation->status->isTerminal() || $operation->status === BulkOperationStatus::Cancelling) {
                return $operation;
            }

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

        if ($scope->actorId !== $definition->actorId) {
            throw new InvalidArgumentException('Bulk actor does not match the durable scope.');
        }

        return $scope;
    }

    private function assertScope(DataScope $scope): void
    {
        $context = $this->contexts->restore($scope);
        $actual = $context->isBoxed()
            ? 'boxed:'.$context->account->id
            : $this->connection->getDriverName().':'.$this->connection->getDatabaseName();

        if (! hash_equals($scope->connectionIdentity, $actual)) {
            throw ScopeContextMismatch::connection(expected: $scope->connectionIdentity, actual: $actual);
        }
    }

    private function scopeQuery(Builder $query, DataScope $scope): Builder
    {
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
            inputRef: JsonDocuments::capture($this->objectArray(value: DatabaseValue::field(row: $row, name: 'input_ref'), name: 'input reference')),
        );

        return new BulkOperation(
            definition: $definition,
            transferRef: JsonDocuments::nullable($this->nullableObjectArray(value: DatabaseValue::field(row: $row, name: 'transfer_ref'), name: 'transfer reference')),
            status: BulkOperationStatus::from(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'status'))),
            terminalStatus: DatabaseValue::field(row: $row, name: 'terminal_status') === null
                ? null
                : BulkOperationStatus::from(DatabaseValue::text(DatabaseValue::field(row: $row, name: 'terminal_status'))),
            revision: DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'revision')),
            leaseOwner: DatabaseValue::field(row: $row, name: 'lease_owner') === null ? null : DatabaseValue::text(DatabaseValue::field(row: $row, name: 'lease_owner')),
            leaseUntil: $this->date(DatabaseValue::field(row: $row, name: 'lease_until')),
            cancelRequestedAt: $this->date(DatabaseValue::field(row: $row, name: 'cancel_requested_at')),
            checkpoint: JsonDocuments::capture($this->objectArray(value: DatabaseValue::field(row: $row, name: 'checkpoint'), name: 'checkpoint')),
            progress: JsonDocuments::capture($this->objectArray(value: DatabaseValue::field(row: $row, name: 'progress'), name: 'progress')),
            resultRef: JsonDocuments::nullable($this->nullableObjectArray(value: DatabaseValue::field(row: $row, name: 'result_ref'), name: 'result reference')),
            error: JsonDocuments::nullable($this->nullableObjectArray(value: DatabaseValue::field(row: $row, name: 'error'), name: 'error')),
            attempt: DatabaseValue::integer(DatabaseValue::field(row: $row, name: 'attempt')),
            nextAttemptAt: $this->requiredDate(value: DatabaseValue::field(row: $row, name: 'next_attempt_at'), name: 'next attempt'),
            createdAt: $this->requiredDate(value: DatabaseValue::field(row: $row, name: 'created_at'), name: 'created'),
            finishedAt: $this->date(DatabaseValue::field(row: $row, name: 'finished_at')),
            expiresAt: $this->date(DatabaseValue::field(row: $row, name: 'expires_at')),
        );
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
