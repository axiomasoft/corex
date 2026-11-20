<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use Closure;
use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Contracts\ScopeAuthority;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Context\Exceptions\ScopeAuthorityUnavailable;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final readonly class DatabaseScopeControl implements ScopeAdmission, ScopeAuthority
{
    public function __construct(private DatabaseManager $database, private ScopeOptions $options, private DataScopeCodec $codec, private ?ControlStoreIsolation $isolation = null) {}

    public function resolve(AccountRef $account): PhysicalBinding
    {
        return $this->binding($this->route($account->id)->first());
    }

    public function register(string $accountId, PhysicalBinding $binding): void
    {
        if ($binding->deploymentId !== $this->deployment() || $binding->controlRevision !== 1 || $binding->mode !== 'open') {
            throw new ScopeContextMismatch('Invalid initial route.');
        }
        $this->independent()->table('corex_scope_routes')->insert(['account_id' => $accountId, 'used_epochs' => json_encode([$binding->restoreEpoch], JSON_THROW_ON_ERROR), ...$this->values($binding)]);
    }

    public function acquire(DataScope $scope, OperationKey $key, AdmissionKind $kind): AdmissionTicket
    {
        $control = $this->independent();

        return $control->transaction(function () use ($scope, $key, $kind, $control): AdmissionTicket {
            $binding = $this->binding($this->route($scope->accountId)->lockForUpdate()->first());
            $binding->assertScope($scope);

            if ($binding->mode !== 'open') {
                throw new ScopeContextMismatch('Scope admission is closed.');
            }

            if ($kind === AdmissionKind::ProviderSend && ($key->effectKey === null || $key->deadline === null)) {
                throw new ScopeContextMismatch('Provider admission requires an effect key and a deadline.');
            }
            $id = (string) Str::uuid7();
            $control->table('corex_scope_tickets')->insert([
                'ticket_id' => $id, 'deployment_id' => $scope->deploymentId, 'account_id' => $scope->accountId,
                'binding_id' => $scope->bindingId, 'binding_generation' => $scope->bindingGeneration,
                'restore_epoch' => $scope->restoreEpoch, 'control_revision' => $binding->controlRevision,
                'scope' => json_encode($this->codec->encode($scope), JSON_THROW_ON_ERROR),
                'operation_id' => $key->id, 'attempt' => $key->attempt, 'kind' => $kind->value,
                'effect_key' => $key->effectKey, 'deadline' => $key->deadline, 'state' => 'outstanding',
            ]);

            return new AdmissionTicket($id, $scope, $binding->controlRevision, $key, $kind);
        });
    }

    public function settle(AdmissionTicket $ticket, AttemptEvidence $evidence): void
    {
        $control = $this->independent();
        $control->transaction(function () use ($ticket, $evidence, $control): void {
            $query = $control->table('corex_scope_tickets')->where('ticket_id', $ticket->id)
                ->where('deployment_id', $this->deployment())->where('account_id', $ticket->scope->accountId)
                ->where('control_revision', $ticket->controlRevision)->where('kind', $ticket->kind->value)
                ->where('operation_id', $ticket->key->id)->where('attempt', $ticket->key->attempt);
            $row = $query->lockForUpdate()->first();

            if ($row === null || ! is_string($row->scope) || json_decode($row->scope, true, flags: JSON_THROW_ON_ERROR) !== $this->codec->encode($ticket->scope)) {
                throw new ScopeContextMismatch('Admission ticket is unknown.');
            }

            if ($row->state === $evidence->outcome && $row->evidence === $evidence->reference) {
                return;
            }

            if ($row->state !== 'outstanding' && ! ($row->state === 'unknown' && in_array($evidence->outcome, ['reconciled', 'isolated'], true))) {
                throw new ScopeContextMismatch('Conflicting admission evidence.');
            }
            $query->update(['state' => $evidence->outcome, 'evidence' => $evidence->reference]);
        });
    }

    public function close(string $accountId, int $expectedRevision): int
    {
        return $this->transition($accountId, $expectedRevision, 'open', static fn (): array => ['mode' => 'closing']);
    }

    public function drain(string $accountId, int $expectedRevision): int
    {
        return $this->transition($accountId, $expectedRevision, 'closing', function () use ($accountId): array {
            if ($this->connection()->table('corex_scope_tickets')->where('deployment_id', $this->deployment())
                ->where('account_id', $accountId)->whereIn('state', ['outstanding', 'unknown'])->exists()) {
                throw new ScopeContextMismatch('Outstanding work must be drained or reconciled.');
            }

            return ['mode' => 'closed'];
        });
    }

    /** @param Closure(PhysicalBinding):bool $verify Verifies the new physical owner, schema and isolation before reopening. */
    public function reopen(string $accountId, int $expectedRevision, PhysicalBinding $binding, Closure $verify): int
    {
        return $this->transition($accountId, $expectedRevision, 'closed', function (PhysicalBinding $old) use ($binding, $verify, $accountId): array {
            $history = $this->route($accountId)->value('used_epochs');
            $used = is_string($history) ? json_decode($history, true, flags: JSON_THROW_ON_ERROR) : null;

            if (! is_array($used) || ! array_is_list($used)) {
                throw new ScopeAuthorityUnavailable('The external recovery history is unavailable.');
            }

            if ($binding->deploymentId !== $old->deploymentId || $binding->bindingGeneration < $old->bindingGeneration
                || (($binding->bindingId !== $old->bindingId || $binding->accountIncarnation !== $old->accountIncarnation) && $binding->bindingGeneration <= $old->bindingGeneration)
                || ($binding->bindingGeneration === $old->bindingGeneration && $binding->restoreEpoch === $old->restoreEpoch && $binding->accountIncarnation === $old->accountIncarnation)
                || ($binding->restoreEpoch !== $old->restoreEpoch && in_array($binding->restoreEpoch, $used, true))
                || $verify($binding) !== true) {
                throw new ScopeContextMismatch('A verified new binding fence is required.');
            }

            if ($binding->restoreEpoch !== $old->restoreEpoch) {
                $used[] = $binding->restoreEpoch;
            }

            return [...$this->values($binding), 'mode' => 'open', 'used_epochs' => json_encode($used, JSON_THROW_ON_ERROR)];
        });
    }

    /** @param Closure(PhysicalBinding):array<string, int|string> $change */
    private function transition(string $accountId, int $expectedRevision, string $mode, Closure $change): int
    {
        return $this->independent()->transaction(function () use ($accountId, $expectedRevision, $mode, $change): int {
            $route = $this->route($accountId);
            $binding = $this->binding($route->lockForUpdate()->first());

            if ($binding->mode !== $mode || $binding->controlRevision !== $expectedRevision) {
                throw new ScopeContextMismatch('Concurrent change to scope admission.');
            }
            $revision = $expectedRevision + 1;
            $route->update([...$change($binding), 'control_revision' => $revision]);

            return $revision;
        });
    }

    private function route(string $accountId): Builder
    {
        return $this->connection()->table('corex_scope_routes')->where('deployment_id', $this->deployment())->where('account_id', $accountId);
    }

    private function deployment(): string
    {
        $deployment = $this->options->deploymentId;

        return is_string($deployment) && $deployment !== '' ? $deployment : throw new ScopeAuthorityUnavailable('Missing scope deployment identity.');
    }

    private function independent(): Connection
    {
        $control = $this->connection();

        if ($control->transactionLevel() !== 0) {
            throw new ScopeAuthorityUnavailable('Scope admission requires an independently committed control transaction.');
        }

        return $control;
    }

    private function connection(): Connection
    {
        $name = $this->options->controlConnection;

        if (! is_string($name) || $name === '') {
            throw new ScopeAuthorityUnavailable('An independent scope control connection is required.');
        }
        $connection = $this->database->connection($name);
        ($this->isolation ?? throw new ScopeAuthorityUnavailable('The external control owner is required.'))->verify($connection, $this->deployment());

        return $connection;
    }

    private function binding(?object $row): PhysicalBinding
    {
        if ($row === null) {
            throw new ScopeContextMismatch('Unknown scope binding.');
        }
        $raw = get_object_vars($row);

        return new PhysicalBinding($this->text($raw, 'deployment_id'), $this->text($raw, 'binding_id'), $this->number($raw, 'binding_generation'),
            $this->text($raw, 'account_incarnation'), $this->text($raw, 'restore_epoch'), $this->text($raw, 'mode'), $this->number($raw, 'control_revision'));
    }

    /** @param array<array-key,mixed> $row */
    private function text(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) ? $value : throw new ScopeAuthorityUnavailable('Invalid owner control record.');
    }

    /** @param array<array-key,mixed> $row */
    private function number(array $row, string $key): int
    {
        $value = filter_var($row[$key] ?? null, FILTER_VALIDATE_INT);

        return is_int($value) ? $value : throw new ScopeAuthorityUnavailable('Invalid owner control record.');
    }

    /** @return array<string, int|string> */
    private function values(PhysicalBinding $binding): array
    {
        return ['deployment_id' => $binding->deploymentId, 'binding_id' => $binding->bindingId,
            'binding_generation' => $binding->bindingGeneration, 'account_incarnation' => $binding->accountIncarnation,
            'restore_epoch' => $binding->restoreEpoch, 'mode' => $binding->mode, 'control_revision' => $binding->controlRevision];
    }
}
