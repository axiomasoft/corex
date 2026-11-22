<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use Closure;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\PhysicalBindingVerifier;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderEffectRecovery;
use CoreX\Context\Contracts\ProviderReceiptProjector;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Contracts\ScopeAuthority;
use CoreX\Context\Contracts\StoredWorkRunner;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Contracts\WorkScopeDirectory;
use CoreX\Context\Contracts\WorkScopeRunner;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\StoredWorkProof;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Context\Laravel\DatabaseScopeControl;
use CoreX\Data\Data\DataScope;
use Illuminate\Container\Container;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Packman\Attributes\Internal;
use Throwable;

#[Internal]
final readonly class VerifiedWorkScope implements CurrentWorkScope, DataScopeFactory, ProviderEffectRecovery, StoredWorkRunner, WorkScopeRunner
{
    /** @param Closure():Container $application */
    public function __construct(private Closure $application) {}

    public function forCurrentPrincipal(TenantContext $context): DataScope
    {
        $actual = ($this->application)()->make(TenantContextResolver::class)->current();
        $this->checkLogical(context: $actual, account: $context->account->id, workspace: $context->workspace?->id);
        $binding = $this->binding($actual);
        $this->verify(binding: $binding, context: $actual);
        $principal = ($this->application)()->make(WorkPrincipalAuthority::class)->current($actual);

        return new DataScope(deploymentId: $binding->deploymentId, accountId: $actual->account->id, workspaceId: $actual->workspace?->id, bindingId: $binding->bindingId,
            bindingGeneration: $binding->bindingGeneration, accountIncarnation: $binding->accountIncarnation, restoreEpoch: $binding->restoreEpoch, principalKind: $principal->kind, principalId: $principal->id);
    }

    public function assert(DataScope $scope, ?ConnectionInterface $connection = null): TenantContext
    {
        $context = ($this->application)()->make(TenantContextResolver::class)->current();
        $this->checkLogical(context: $context, account: $scope->accountId, workspace: $scope->workspaceId);
        $binding = ($this->application)()->make(ScopeAuthority::class)->resolve($context->account);
        $binding->assertScope($scope);
        $actual = $this->verify(binding: $binding, context: $context);

        if ($connection !== null && $actual !== $connection) {
            throw new ScopeContextMismatch('Work connection does not belong to the active physical owner.');
        }
        $transactions = ($this->application)()->make(LocalWorkTransactions::class);

        if ($binding->mode !== 'open' && ! $transactions->holds($scope, $actual)) {
            throw new ScopeContextMismatch('Scope admission is closed.');
        }
        $principal = ($this->application)()->make(WorkPrincipalAuthority::class)->current($context);

        if ($principal->kind !== $scope->principalKind || $principal->id !== $scope->principalId) {
            throw new ScopeContextMismatch('Current work principal does not match the requested scope.');
        }

        if ($actual->transactionLevel() > 0) {
            $transactions->pin(($this->application)()->make(ScopeAdmission::class), $scope, $actual);
        }

        return $context;
    }

    public function run(DataScope $scope, Closure $work): mixed
    {
        return $this->execute(scope: $scope, work: $work, durable: false);
    }

    public function runStored(DataScope $scope, StoredWorkProof $proof, Closure $work, ?OperationKey $attempt = null): mixed
    {
        ($this->application)()->make(StoredWorkOrigins::class)->verify($scope, $proof);

        return $this->execute(scope: $scope, work: $work, durable: true, attempt: $attempt);
    }

    public function assertReceipt(ProviderEffectStatus $status, ConnectionInterface $connection): TenantContext
    {
        $scope = $status->ticket->scope;
        $context = ($this->application)()->make(TenantContextResolver::class)->current();
        $this->checkLogical(context: $context, account: $scope->accountId, workspace: $scope->workspaceId);
        $binding = ($this->application)()->make(ScopeAuthority::class)->resolve($context->account);
        $binding->assertScope($scope);
        $principal = ($this->application)()->make(WorkPrincipalAuthority::class)->current($context);

        if ($status->effect->identity() !== $status->ticket->key->id || $principal->kind !== $scope->principalKind || $principal->id !== $scope->principalId
            || $this->verify(binding: $binding, context: $context) !== $connection || ! ($this->application)()->make(LocalWorkTransactions::class)->holdsReceipt($status->ticket, $connection)) {
            throw new ScopeContextMismatch('This receipt does not belong to the verified projector frame.');
        }

        return $context;
    }

    public function recover(ProviderEffectRef $effect): bool
    {
        $ledger = ($this->application)()->make(ProviderEffectLedger::class);
        $initial = $ledger->inspect($effect);

        if ($initial === null || $initial->state === 'isolated') {
            return false;
        }

        return $this->execute(scope: $initial->ticket->scope, work: function () use ($effect, $ledger, $initial): bool {
            $projector = null;
            foreach (($this->application)()->tagged(ProviderReceiptProjector::class) as $candidate) {
                if ($candidate instanceof ProviderReceiptProjector && $candidate->owner() === $effect->owner) {
                    if ($projector !== null) {
                        throw new ScopeContextMismatch('Duplicate provider receipt projector.');
                    }
                    $projector = $candidate;
                }
            }

            if ($projector === null) {
                if ($initial->state === 'conflicting') {
                    return false;
                }

                throw new ScopeContextMismatch('Missing provider receipt projector.');
            }
            $status = $ledger->inspect($effect);

            if ($status === null || $status->state === 'isolated' || $status->ticket->id !== $initial->ticket->id) {
                return false;
            }

            if ($status->state !== 'conflicting' && in_array($status->observation?->kind, [null, ProviderObservationKind::Unknown, ProviderObservationKind::Accepted], true)) {
                $found = $projector->lookup($status);

                if ($found !== null) {
                    $ledger->observe($status->ticket, $found);
                }
                $status = $ledger->inspect($effect);
            }

            if ($status === null || $status->state === 'isolated' || $status->ticket->id !== $initial->ticket->id || ($status->state !== 'conflicting' && in_array($status->observation?->kind, [null, ProviderObservationKind::Unknown], true))) {
                return false;
            }
            $receipt = $projector->project($status);

            if (($this->application)()->make(DatabaseManager::class)->connection()->transactionLevel() !== 0) {
                throw new ScopeContextMismatch('Provider receipt projection has not committed.');
            }

            if ($status->state === 'conflicting' || $receipt === null) {
                return false;
            }

            if ($status->state !== 'reconciled') {
                ($this->application)()->make(ScopeAdmission::class)->settle($status->ticket, new AttemptEvidence(outcome: 'reconciled', reference: $receipt));
            }

            return true;
        }, durable: true, provider: $initial->ticket);
    }

    /**
     * @template T
     *
     * @param  Closure(TenantContext): T  $work
     * @return T
     */
    private function execute(DataScope $scope, Closure $work, bool $durable, ?OperationKey $attempt = null, ?AdmissionTicket $provider = null): mixed
    {
        $health = ($this->application)()->make(WorkProcessHealth::class);
        $health->check();
        $manager = ($this->application)()->make(TenancyManager::class);
        $previous = $manager->context();
        $database = ($this->application)()->make(DatabaseManager::class);
        $outerName = $database->getDefaultConnection();
        // A central process may retain a tenant-only default alias. Only an active
        // tenant frame can open it. A cached central connection can still own a transaction.
        $outerConnection = $previous === null
            ? ($database->getConnections()[$outerName] ?? null)
            : $database->connection();
        $switch = $previous?->account->id !== $scope->accountId || $previous->workspace?->id !== $scope->workspaceId;

        if (($switch || $provider !== null) && $outerConnection !== null && $outerConnection->transactionLevel() > 0) {
            throw new ScopeContextMismatch('A live business transaction cannot switch its physical or logical work frame.');
        }
        $outerBinding = $previous === null ? null : ($this->application)()->make(ScopeAuthority::class)->resolve($previous->account);

        if (! $durable) {
            if ($previous === null) {
                throw new ScopeContextMismatch('An authenticated work context is required.');
            }
            $principal = ($this->application)()->make(WorkPrincipalAuthority::class)->current($previous);

            if ($principal->kind !== $scope->principalKind || $principal->id !== $scope->principalId) {
                throw new ScopeContextMismatch('Work principal does not match the authenticated principal.');
            }
        }
        $admission = ($this->application)()->make(ScopeAdmission::class);
        $ticket = $provider === null
            ? $admission->acquire($scope, $attempt ?? new OperationKey(id: (string) Str::uuid7(), attempt: 1), AdmissionKind::LocalWrite)
            : ($this->application)()->make(DatabaseScopeControl::class)->receiptTicket($provider);
        $transactions = ($this->application)()->make(LocalWorkTransactions::class);

        $success = false;

        try {
            $directory = ($this->application)()->make(WorkScopeDirectory::class);
            $account = $directory->account($scope->accountId);

            if (! in_array($account->status, ['active', 'trial'], true)) {
                throw new ScopeContextMismatch('Account is unavailable for business work.');
            }
            $binding = ($this->application)()->make(ScopeAuthority::class)->resolve($account);
            $binding->assertScope($scope);
            $manager = ($this->application)()->make(TenancyManager::class);
            $invoke = function () use ($scope, $binding, $durable, $work, $transactions, $admission, $ticket): mixed {
                $context = ($this->application)()->make(TenantContextResolver::class)->current();
                $this->checkLogical(context: $context, account: $scope->accountId, workspace: $scope->workspaceId);
                $connection = $this->verify(binding: $binding, context: $context);

                if ($durable) {
                    ($this->application)()->forgetScopedInstances();
                }

                try {
                    return ($this->application)()->make(WorkPrincipalAuthority::class)->run($scope, $context, $durable, function () use ($transactions, $ticket, $admission, $connection, $work, $context): mixed {
                        $transactions->enter($admission, $ticket, $connection);

                        return $work($context);
                    });
                } finally {
                    if ($durable) {
                        ($this->application)()->forgetScopedInstances();
                    }
                }
            };
            $current = $manager->context();

            if ($current?->account->id === $account->id && $current->workspace?->id === $scope->workspaceId) {
                $result = $invoke();
            } elseif ($scope->workspaceId === null) {
                $result = $manager->runFor($account, null, $invoke);
            } else {
                $result = $manager->runFor($account, null, function () use ($scope, $binding, $account, $manager, $directory, $invoke): mixed {
                    $this->verify(binding: $binding, context: ($this->application)()->make(TenantContextResolver::class)->current());

                    return $manager->runFor($account, $directory->workspace($scope->workspaceId), $invoke);
                });
            }
            $success = true;

            return $result;
        } finally {
            try {
                $restored = $manager->context();

                if ($database->getDefaultConnection() !== $outerName || $restored?->account->id !== $previous?->account->id || $restored?->workspace?->id !== $previous?->workspace?->id) {
                    throw new ScopeContextMismatch('Previous work frame was not restored.');
                }

                if ($outerBinding !== null && $restored !== null) {
                    $this->verify(binding: $outerBinding, context: $restored);
                } elseif ($outerConnection !== null && ($database->getConnections()[$outerName] ?? null) !== $outerConnection) {
                    throw new ScopeContextMismatch('The central connection was not restored.');
                }
            } catch (Throwable $failure) {
                $success = false;
                $health->stop(($this->application)());

                throw $failure;
            } finally {
                $transactions->leave($admission, $ticket, $success);
            }
        }
    }

    private function binding(TenantContext $context): PhysicalBinding
    {
        $binding = ($this->application)()->make(ScopeAuthority::class)->resolve($context->account);

        if ($binding->mode !== 'open') {
            throw new ScopeContextMismatch('Scope admission is closed.');
        }

        return $binding;
    }

    private function verify(PhysicalBinding $binding, TenantContext $context): ConnectionInterface
    {
        $connection = ($this->application)()->make(DatabaseManager::class)->connection();
        ($this->application)()->make(PhysicalBindingVerifier::class)->verify($binding, $context, $connection);

        return $connection;
    }

    private function checkLogical(TenantContext $context, string $account, ?string $workspace): void
    {
        if ($context->account->id !== $account || $context->workspace?->id !== $workspace) {
            throw new ScopeContextMismatch('The requested account or workspace differs from the active owner.');
        }
    }
}
