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

            if (! $this->sameSubmission($definition, $stored->definition)) {
                throw new RuntimeException('Bulk submission conflicts with the existing durable definition.');
            }

            return $stored;
        }, $this->transactionAttempts());
    }

    #[Override]
    public function find(DataScope $scope, string $id): ?BulkOperation
    {
        $this->assertScope($scope);
        $this->assertIdentifier($id, 'operation id');

        $row = $this->connection->table(self::Table)->where('id', $id)->first();

        if ($row === null || ! $this->rowMatchesScope($row, $scope)) {
            return null;
        }

        return $this->operation($row);
    }

    #[Override]
    public function claim(DataScope $scope, string $worker): ?BulkOperation
    {
        $this->assertScope($scope);
        $this->assertIdentifier($worker, 'worker');

        return $this->connection->transaction(function () use ($scope, $worker): ?BulkOperation {
            $now = $this->clock->now();
            $query = $this->scopeQuery($this->connection->table(self::Table), $scope)
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
                ->orderBy('next_attempt_at')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(1);

            $this->lockClaimQuery($query);
            $row = $query->first();

            if ($row === null) {
                return null;
            }

            $status = BulkOperationStatus::from((string) $row->status);
            $nextStatus = $status === BulkOperationStatus::Pending
                ? BulkOperationStatus::Running
                : $status;
            $leaseUntil = $now->add(new DateInterval("PT{$this->leaseSeconds}S"));
            $changed = $this->connection->table(self::Table)
                ->where('id', (string) $row->id)
                ->where('status', $status->value)
                ->where('revision', (int) $row->revision)
                ->update([
                    'status' => $nextStatus->value,
                    'revision' => (int) $row->revision + 1,
                    'lease_owner' => $worker,
                    'lease_until' => $leaseUntil,
                    'attempt' => (int) $row->attempt + 1,
                ]);

            if ($changed !== 1) {
                throw new RuntimeException('Bulk claim lost its compare-and-swap fence.');
            }

            return $this->operation($this->requiredRow((string) $row->id));
        }, $this->transactionAttempts());
    }

    #[Override]
    public function save(BulkOperation $operation, string $leaseOwner, int $revision): bool
    {
        $scope = $this->definitionScope($operation->definition);
        $this->assertScope($scope);
        $this->assertIdentifier($leaseOwner, 'lease owner');

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

            if ($row === null || ! $this->rowMatchesScope($row, $scope)) {
                return false;
            }

            $leaseUntil = $this->date($row->lease_until);

            if ($leaseUntil === null || $leaseUntil <= $now) {
                return false;
            }

            $stored = $this->operation($row);

            if (! $this->sameDefinition($stored->definition, $operation->definition)) {
                throw new InvalidArgumentException('Bulk durable definition is immutable.');
            }

            $this->assertTransition($stored->status, $operation->status);
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
        $this->assertIdentifier($id, 'operation id');

        return $this->connection->transaction(function () use ($id, $scope): BulkOperation {
            $row = $this->connection->table(self::Table)->where('id', $id)->lockForUpdate()->first();

            if ($row === null || ! $this->rowMatchesScope($row, $scope)) {
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
            JsonCanonical::encode($this->scopeArray($row->scope)),
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
        return $stored->id === $requested->id && $this->sameSubmission($requested, $stored);
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
            id: (string) $row->id,
            submissionId: (string) $row->submission_id,
            operationId: (string) $row->operation_id,
            major: (string) $row->major,
            contractHash: (string) $row->contract_hash,
            encodedScope: $this->canonicalScopeArray($row->scope),
            actorId: (string) $row->actor_id,
            clientId: (string) $row->client_id,
            extensionStamp: JsonDocuments::capture($this->objectArray($row->extension_stamp, 'extension stamp')),
            inputRef: JsonDocuments::capture($this->objectArray($row->input_ref, 'input reference')),
        );

        return new BulkOperation(
            definition: $definition,
            transferRef: JsonDocuments::nullable($this->nullableObjectArray($row->transfer_ref, 'transfer reference')),
            status: BulkOperationStatus::from((string) $row->status),
            terminalStatus: $row->terminal_status === null
                ? null
                : BulkOperationStatus::from((string) $row->terminal_status),
            revision: (int) $row->revision,
            leaseOwner: $row->lease_owner === null ? null : (string) $row->lease_owner,
            leaseUntil: $this->date($row->lease_until),
            cancelRequestedAt: $this->date($row->cancel_requested_at),
            checkpoint: JsonDocuments::capture($this->objectArray($row->checkpoint, 'checkpoint')),
            progress: JsonDocuments::capture($this->objectArray($row->progress, 'progress')),
            resultRef: JsonDocuments::nullable($this->nullableObjectArray($row->result_ref, 'result reference')),
            error: JsonDocuments::nullable($this->nullableObjectArray($row->error, 'error')),
            attempt: (int) $row->attempt,
            nextAttemptAt: $this->requiredDate($row->next_attempt_at, 'next attempt'),
            createdAt: $this->requiredDate($row->created_at, 'created'),
            finishedAt: $this->date($row->finished_at),
            expiresAt: $this->date($row->expires_at),
        );
    }

    /** @return array<string, scalar|null> */
    private function scopeArray(mixed $value): array
    {
        $decoded = $this->decodedArray($value, 'scope');

        foreach ($decoded as $key => $item) {
            if (! is_string($key) || ! is_scalar($item) && $item !== null) {
                throw new RuntimeException('Bulk scope contains a non-scalar value.');
            }
        }

        /** @var array<string, scalar|null> $decoded */
        return $decoded;
    }

    /** @return array<string, scalar|null> */
    private function canonicalScopeArray(mixed $value): array
    {
        return $this->scopeCodec->encode($this->scopeCodec->decode($this->scopeArray($value)));
    }

    /** @return array<string, mixed> */
    private function objectArray(mixed $value, string $name): array
    {
        $decoded = $this->decodedArray($value, $name);

        if ($decoded !== [] && array_is_list($decoded)) {
            throw new RuntimeException("Bulk {$name} must be a JSON object.");
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /** @return array<string, mixed>|null */
    private function nullableObjectArray(mixed $value, string $name): ?array
    {
        return $value === null ? null : $this->objectArray($value, $name);
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
            : new DateTimeImmutable((string) $value);
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

    private function transactionAttempts(): int
    {
        return $this->connection->getDriverName() === 'pgsql' ? 3 : 1;
    }
}
