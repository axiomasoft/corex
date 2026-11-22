<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use Closure;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Data\Data\DataScope;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;

/** Keep a local ticket alive across a caller-owned transaction, on exactly its connection. */
#[Internal]
final class LocalWorkTransactions
{
    /** @var array<string,array{connection:ConnectionInterface,admission:ScopeAdmission,ticket:AdmissionTicket,waiting:bool,outside:bool,rollback:bool}> */
    private array $attempts = [];

    public function localTicket(DataScope $scope, ConnectionInterface $connection): ?AdmissionTicket
    {
        foreach ($this->attempts as $attempt) {
            if ($attempt['ticket']->kind === AdmissionKind::LocalWrite && $attempt['connection'] === $connection && get_object_vars($attempt['ticket']->scope) === get_object_vars($scope)) {
                return $attempt['ticket'];
            }
        }

        return null;
    }

    public function holds(DataScope $scope, ConnectionInterface $connection): bool
    {
        return $this->localTicket(scope: $scope, connection: $connection) !== null;
    }

    public function holdsReceipt(AdmissionTicket $provider, ConnectionInterface $connection): bool
    {
        foreach ($this->attempts as $attempt) {
            $ticket = $attempt['ticket'];

            if (! $attempt['waiting'] && $ticket->kind === AdmissionKind::ReceiptProjection && $attempt['connection'] === $connection
                && $ticket->key->effectKey === $provider->id && get_object_vars($ticket->scope) === get_object_vars($provider->scope)) {
                return true;
            }
        }

        return false;
    }

    public function pin(ScopeAdmission $admission, DataScope $scope, ConnectionInterface $connection): void
    {
        if ($connection->transactionLevel() === 0 || $this->holds(scope: $scope, connection: $connection)) {
            return;
        }
        $ticket = $admission->acquire(scope: $scope, key: new OperationKey(id: (string) Str::uuid7(), attempt: 1), kind: AdmissionKind::LocalWrite);
        $this->enter(admission: $admission, ticket: $ticket, connection: $connection);
        $this->attempts[$ticket->id]['waiting'] = true;
    }

    public function enter(ScopeAdmission $admission, AdmissionTicket $ticket, ConnectionInterface $connection): void
    {
        $this->attempts[$ticket->id] = ['connection' => $connection, 'admission' => $admission, 'ticket' => $ticket,
            'waiting' => false, 'outside' => false, 'rollback' => false];
    }

    public function query(QueryExecuted $event): void
    {
        if ($event->connection->transactionLevel() !== 0) {
            return;
        }
        foreach ($this->attempts as $id => $attempt) {
            if ($attempt['connection'] === $event->connection && ! $attempt['waiting']) {
                $this->attempts[$id]['outside'] = true;
            }
        }
    }

    public function completed(TransactionCommitted|TransactionRolledBack $event): void
    {
        if ($event->connection->transactionLevel() !== 0) {
            return;
        }
        foreach ($this->attempts as $id => $attempt) {
            if ($attempt['connection'] !== $event->connection) {
                continue;
            }
            $rollback = $event instanceof TransactionRolledBack;

            if ($attempt['waiting']) {
                $this->settle(id: $id, outcome: $rollback && ! $attempt['outside'] ? 'rolled-back' : 'committed');
            } elseif ($rollback) {
                $this->attempts[$id]['rollback'] = true;
            } else {
                $this->attempts[$id]['outside'] = true;
            }
        }
    }

    /** Capture only the pin waiting for this outer transaction's committed event.
     * @return (Closure():void)|null Call only with a durable commit witness.
     */
    public function commitConfirmation(DataScope $scope, ConnectionInterface $connection): ?Closure
    {
        $ticket = $this->localTicket(scope: $scope, connection: $connection);

        if ($ticket === null || ! $this->attempts[$ticket->id]['waiting']) {
            return null;
        }

        return function () use ($ticket, $connection): void {
            $attempt = $this->attempts[$ticket->id] ?? null;

            if ($attempt !== null && $attempt['ticket'] === $ticket && $connection->transactionLevel() === 0) {
                $attempt['admission']->settle($ticket, new AttemptEvidence(outcome: 'committed', reference: $ticket->id));
                unset($this->attempts[$ticket->id]);
            }
        };
    }

    public function leave(ScopeAdmission $admission, AdmissionTicket $ticket, bool $success): void
    {
        $attempt = $this->attempts[$ticket->id] ?? null;

        if ($attempt === null) {
            $admission->settle(ticket: $ticket, evidence: new AttemptEvidence(outcome: 'rolled-back', reference: $ticket->id));

            return;
        }

        if ($attempt['connection']->transactionLevel() > 0) {
            $this->attempts[$ticket->id]['waiting'] = true;

            return;
        }
        $state = $success ? 'committed' : 'unknown';

        if ($attempt['rollback'] && ! $attempt['outside']) {
            $state = 'rolled-back';
        }
        $this->settle(id: $ticket->id, outcome: $state);
    }

    private function settle(string $id, string $outcome): void
    {
        $attempt = $this->attempts[$id];
        unset($this->attempts[$id]);
        $attempt['admission']->settle($attempt['ticket'], new AttemptEvidence(outcome: $outcome, reference: $id));
    }
}
