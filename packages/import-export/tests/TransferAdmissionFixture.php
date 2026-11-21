<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Tests;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Contracts\ReferenceAdmission;
use CoreX\Foundation\Data\ModuleRef;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use LogicException;
use Mockery;

/** Unit composition for public ports with a local fixture authority. Installed proofs use real control storage. */
final class TransferAdmissionFixture
{
    public static function bind(Application $app): void
    {
        $gate = Mockery::mock(ReferenceAdmission::class);
        $gate->shouldReceive('enlist')->andReturnUsing(static function (DataScope $scope, ConnectionInterface $connection, ModuleRef $target) use ($app): void {
            if ($connection->transactionLevel() < 1 || $target->package !== 'axiomasoft/corex-import-export') {
                throw new LogicException('The transfer must enlist its real owner transaction.');
            }
            $app->make(CurrentWorkScope::class)->assert($scope, $connection);
        });
        $gate->shouldReceive('reserve')->andReturnUsing(static function (DataScope $scope, ProviderAttempt $attempt, ModuleRef $target) use ($app): AdmissionTicket {
            if ($target->package !== 'axiomasoft/corex-import-export') {
                throw new LogicException('Unexpected effect owner.');
            }
            $app->make(CurrentWorkScope::class)->assert($scope);

            return new AdmissionTicket($attempt->claimToken, $scope, 1, new OperationKey($attempt->ownerRef, $attempt->attempt, $attempt->effect->effectKey, $attempt->deadline), AdmissionKind::ProviderSend);
        });
        $app->instance(ReferenceAdmission::class, $gate);
        $effects = Mockery::mock(ProviderEffectLedger::class);
        $effects->shouldReceive('inspect')->andReturnNull();
        $effects->shouldReceive('observe')->withArgs(static fn (AdmissionTicket $ticket): bool => $ticket->kind === AdmissionKind::ProviderSend);
        $app->instance(ProviderEffectLedger::class, $effects);
        $tickets = Mockery::mock(ScopeAdmission::class);
        $tickets->shouldReceive('settle')->withArgs(static fn (AdmissionTicket $ticket): bool => $ticket->kind === AdmissionKind::ProviderSend);
        $app->instance(ScopeAdmission::class, $tickets);
    }
}
