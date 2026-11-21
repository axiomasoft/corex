<?php

declare(strict_types=1);

namespace CoreX\Foundation\Contracts;

use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\ModuleRef;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
interface ReferenceAdmission
{
    public function enlist(DataScope $scope, ConnectionInterface $connection, ModuleRef $target, ModuleRef ...$also): void;

    public function reserve(DataScope $scope, ProviderAttempt $attempt, ModuleRef $target, ModuleRef ...$also): AdmissionTicket;
}
