<?php

declare(strict_types=1);

namespace App\Lifecycle;

use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Data\Data\DataScope;

final class ObservedAdmission implements ScopeAdmission
{
    /** @var array<string, AdmissionTicket> */
    public array $tickets = [];

    public function __construct(private readonly ScopeAdmission $owner) {}

    public function acquire(DataScope $scope, OperationKey $key, AdmissionKind $kind): AdmissionTicket
    {
        $ticket = $this->owner->acquire($scope, $key, $kind);

        return $this->tickets[$ticket->id] = $ticket;
    }

    public function settle(AdmissionTicket $ticket, AttemptEvidence $evidence): void
    {
        $this->owner->settle($ticket, $evidence);
    }
}
