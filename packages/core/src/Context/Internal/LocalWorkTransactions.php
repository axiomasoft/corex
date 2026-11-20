<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

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

    public function holds(DataScope $scope, ConnectionInterface $connection): bool
    {
        foreach ($this->attempts as $attempt) {
            if ($attempt['connection'] === $connection && get_object_vars($attempt['ticket']->scope) === get_object_vars($scope)) {
                return true;
            }
        }

        return false;
    }

    public function pin(ScopeAdmission $admission, DataScope $scope, ConnectionInterface $connection): void
    {
        if ($connection->transactionLevel() === 0 || $this->holds($scope, $connection)) {
            return;
        }
        $ticket = $admission->acquire($scope, new OperationKey((string) Str::uuid7(), 1), AdmissionKind::LocalWrite);
        $this->enter($admission, $ticket, $connection);
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
                $this->settle($id, $rollback && ! $attempt['outside'] ? 'rolled-back' : 'committed');
            } elseif ($rollback) {
                $this->attempts[$id]['rollback'] = true;
            } else {
                $this->attempts[$id]['outside'] = true;
            }
        }
    }

    public function leave(ScopeAdmission $admission, AdmissionTicket $ticket, bool $success): void
    {
        $attempt = $this->attempts[$ticket->id] ?? null;

        if ($attempt === null) {
            $admission->settle($ticket, new AttemptEvidence('rolled-back', $ticket->id));

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
        $this->settle($ticket->id, $state);
    }

    private function settle(string $id, string $outcome): void
    {
        $attempt = $this->attempts[$id];
        unset($this->attempts[$id]);
        $attempt['admission']->settle($attempt['ticket'], new AttemptEvidence($outcome, $id));
    }
}
