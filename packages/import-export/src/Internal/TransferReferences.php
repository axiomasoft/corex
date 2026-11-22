<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal;

use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;
use Psr\Clock\ClockInterface;

#[Internal]
final readonly class TransferReferences
{
    private const string OWNER = 'axiomasoft/corex-import-export';

    public function __construct(
        private ReferenceAdmission $admission,
        private ProviderEffectLedger $effects,
        private ScopeAdmission $tickets,
        private ClockInterface $clock,
    ) {}

    /** @param array<string, mixed> $stamp */
    public function enlist(DataScope $scope, ConnectionInterface $connection, array $stamp): void
    {
        $this->admission->enlist($scope, $connection, new ModuleRef(self::OWNER), ...$this->targets($stamp));
    }

    public function inspect(DataScope $scope, string $namespace, string $key, string $ownerRef, string $digest): ?ProviderEffectStatus
    {
        $status = $this->effects->inspect($this->ref(scope: $scope, namespace: $namespace, key: $key));

        if ($status !== null && (get_object_vars($status->ticket->scope) !== get_object_vars($scope) || $status->ownerRef !== $ownerRef
            || ! hash_equals($status->digest, $digest) || in_array($status->state, ['conflicting', 'isolated'], true))) {
            throw new SessionUnavailable('Transfer effect belongs to another attempt or scope.');
        }

        return $status;
    }

    /** @param array<string, mixed> $stamp */
    public function reserve(DataScope $scope, string $namespace, string $key, string $ownerRef, string $digest, array $stamp, int $seconds, ?ProviderEffectStatus $previous): AdmissionTicket
    {
        $attempt = ($previous?->ticket->key->attempt ?? 0) + 1;

        return $this->admission->reserve(
            $scope,
            new ProviderAttempt(effect: $this->ref(scope: $scope, namespace: $namespace, key: $key), ownerRef: $ownerRef, claimToken: $key.':'.$attempt, attempt: $attempt, deadline: $this->clock->now()->modify("+{$seconds} seconds"), digest: $digest),
            new ModuleRef(self::OWNER),
            ...$this->targets($stamp),
        );
    }

    public function observe(AdmissionTicket $ticket, ProviderObservationKind $kind, string $reference): void
    {
        $this->effects->observe(ticket: $ticket, observation: new ProviderObservation(kind: $kind, reference: $reference, observer: self::OWNER));

        if ($kind !== ProviderObservationKind::Unknown) {
            $this->tickets->settle(ticket: $ticket, evidence: new AttemptEvidence(outcome: 'reconciled', reference: $reference));
        }
    }

    private function ref(DataScope $scope, string $namespace, string $key): ProviderEffectRef
    {
        return new ProviderEffectRef(accountId: $scope->accountId, accountIncarnation: $scope->accountIncarnation, workspaceId: $scope->workspaceId, owner: self::OWNER, providerNamespace: $namespace, effectKey: $key);
    }

    /** @param array<string, mixed> $stamp
     * @return list<ModuleRef>
     */
    private function targets(array $stamp): array
    {
        $owners = $stamp['owners'] ?? [];

        if (! is_array($owners) || ! array_is_list($owners) || count($owners) > 32) {
            throw new SessionUnavailable('Invalid transfer owner provenance.');
        }

        $targets = [];
        foreach ($owners as $owner) {
            if (! is_string($owner)) {
                throw new SessionUnavailable('Invalid transfer owner provenance.');
            }
            $targets[$owner] = new ModuleRef($owner);
        }

        return array_values($targets);
    }
}
