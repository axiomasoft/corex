<?php

declare(strict_types=1);

namespace CoreX\Context\Contracts;

use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface ScopeAdmission
{
    public function acquire(DataScope $scope, OperationKey $key, AdmissionKind $kind): AdmissionTicket;

    public function settle(AdmissionTicket $ticket, AttemptEvidence $evidence): void;
}
