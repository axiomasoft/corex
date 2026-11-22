<?php

declare(strict_types=1);

namespace CoreX\Foundation\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use CoreX\Foundation\Exceptions\LifecycleBlocked;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DatabaseReferenceAdmission implements ReferenceAdmission
{
    /** @param Closure(DataScope,ConnectionInterface):?AdmissionTicket $ticketFor */
    public function __construct(private CurrentWorkScope $current, private Closure $ticketFor, private DatabaseScopeControl $control, private LifecycleCatalogue $catalogue) {}

    public function enlist(DataScope $scope, ConnectionInterface $connection, ModuleRef $target, ModuleRef ...$also): void
    {
        if ($connection->transactionLevel() < 1) {
            throw new LifecycleBlocked('Reference admission requires the owning transaction.');
        }
        $this->current->assert(scope: $scope, connection: $connection);
        $ticket = ($this->ticketFor)($scope, $connection) ?? throw new LifecycleBlocked('No verified transaction ticket.');
        $this->control->enlistTargets(ticket: $ticket, targets: $this->catalogue->packages($target, ...$also));
    }

    public function reserve(DataScope $scope, ProviderAttempt $attempt, ModuleRef $target, ModuleRef ...$also): AdmissionTicket
    {
        return $this->control->reserveTargets(scope: $scope, attempt: $attempt, targets: $this->catalogue->packages($target, ...$also));
    }
}
