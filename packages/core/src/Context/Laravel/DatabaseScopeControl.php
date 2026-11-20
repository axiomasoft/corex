<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use Closure;
use CoreX\Context\Contracts\ControlStoreIsolation;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\ProviderEffectLedger;
use CoreX\Context\Contracts\ProviderRecoveryAuthority;
use CoreX\Context\Contracts\ScopeAdmission;
use CoreX\Context\Contracts\ScopeAuthority;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\AdmissionTicket;
use CoreX\Context\Data\AttemptEvidence;
use CoreX\Context\Data\OperationKey;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\ProviderAttempt;
use CoreX\Context\Data\ProviderEffectRef;
use CoreX\Context\Data\ProviderEffectStatus;
use CoreX\Context\Data\ProviderObservation;
use CoreX\Context\Enums\AdmissionKind;
use CoreX\Context\Enums\ProviderObservationKind;
use CoreX\Context\Exceptions\CentralContextException;
use CoreX\Context\Exceptions\ScopeAuthorityUnavailable;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Data\Data\DataScope;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Foundation\ApplicationLocator;
use Psr\Clock\ClockInterface;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final readonly class DatabaseScopeControl implements ProviderEffectLedger, ScopeAdmission, ScopeAuthority
{
    public function __construct(private DatabaseManager $database, private ScopeOptions $options, private DataScopeCodec $codec, private ClockInterface $clock, private ?ControlStoreIsolation $isolation = null) {}

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
        if ($kind !== AdmissionKind::LocalWrite) {
            throw new ScopeContextMismatch('Provider sends and receipt projections require their dedicated authority.');
        }

        return $this->issue($scope, $key, $kind, null);
    }

    public function reserve(DataScope $scope, ProviderAttempt $attempt): AdmissionTicket
    {
        ApplicationLocator::current()->make(CurrentWorkScope::class)->assert($scope);
        $effect = $attempt->effect;

        if ($effect->accountId !== $scope->accountId || $effect->workspaceId !== $scope->workspaceId || $effect->accountIncarnation !== $scope->accountIncarnation) {
            throw new ScopeContextMismatch('Provider effect belongs to another logical owner.');
        }

        return $this->issue($scope, new OperationKey($effect->identity(), $attempt->attempt, $effect->effectKey, $attempt->deadline), AdmissionKind::ProviderSend, $attempt);
    }

    /** A receipt ticket can only be installed by the registered recovery dispatcher. */
    public function receiptTicket(AdmissionTicket $provider): AdmissionTicket
    {
        return $this->issue($provider->scope, new OperationKey((string) Str::uuid7(), 1, $provider->id), AdmissionKind::ReceiptProjection, null, $provider);
    }

    private function issue(DataScope $scope, OperationKey $key, AdmissionKind $kind, ?ProviderAttempt $attempt, ?AdmissionTicket $provider = null): AdmissionTicket
    {
        $control = $this->independent();

        return $control->transaction(function () use ($scope, $key, $kind, $control, $attempt, $provider): AdmissionTicket {
            $binding = $this->binding($this->route($scope->accountId)->lockForUpdate()->first());
            $binding->assertScope($scope);

            if ($provider !== null) {
                $linked = $this->ticketRow($control, $provider)->lockForUpdate()->first();
                $this->verifyTicket($provider, $linked);

                if ($linked === null || $linked->provider_identity === null || $linked->state === 'isolated' || $binding->mode === 'closed') {
                    throw new ScopeContextMismatch('Provider receipt recovery is unavailable.');
                }
            } elseif ($binding->mode !== 'open') {
                throw new ScopeContextMismatch('Scope admission is closed.');
            }

            if ($attempt !== null) {
                $prior = $this->providerRows($attempt->effect)->lockForUpdate()->first();

                if ($attempt->deadline <= $this->clock->now() || ($prior !== null && (
                    $prior->state !== 'reconciled' || $prior->observation_kind !== ProviderObservationKind::Absent->value
                    || $prior->effect_digest !== $attempt->digest || $prior->owner_ref !== $attempt->ownerRef
                    || $this->singleInstanceReference($prior->single_instance_reference) !== $attempt->singleInstanceReference
                    || $this->number(get_object_vars($prior), 'attempt') >= $attempt->attempt
                ))) {
                    throw new ScopeContextMismatch('Provider send is expired, conflicting or requires reconciliation.');
                }
            }
            $id = (string) Str::uuid7();
            $control->table('corex_scope_tickets')->insert([
                'ticket_id' => $id, 'deployment_id' => $scope->deploymentId, 'account_id' => $scope->accountId,
                'binding_id' => $scope->bindingId, 'binding_generation' => $scope->bindingGeneration,
                'restore_epoch' => $scope->restoreEpoch, 'control_revision' => $binding->controlRevision,
                'scope' => json_encode($this->codec->encode($scope), JSON_THROW_ON_ERROR),
                'operation_id' => $key->id, 'attempt' => $key->attempt, 'kind' => $kind->value,
                'effect_key' => $key->effectKey, 'deadline' => $key->deadline?->format('Y-m-d H:i:s.uP'), 'state' => 'outstanding',
                'provider_identity' => $attempt?->effect->identity(), 'provider_owner' => $attempt?->effect->owner,
                'provider_namespace' => $attempt?->effect->providerNamespace, 'owner_ref' => $attempt?->ownerRef,
                'claim_token' => $attempt?->claimToken, 'effect_digest' => $attempt?->digest,
                'single_instance_reference' => $attempt->singleInstanceReference ?? false,
            ]);

            return new AdmissionTicket($id, $scope, $binding->controlRevision, $key, $kind);
        });
    }

    private function providerRows(ProviderEffectRef $effect): Builder
    {
        return $this->connection()->table('corex_scope_tickets')->where('deployment_id', $this->deployment())
            ->where('account_id', $effect->accountId)->where('provider_identity', $effect->identity())
            ->orderByRaw("case state when 'conflicting' then 0 when 'isolated' then 1 else 2 end")->orderByDesc('attempt');
    }

    public function inspect(ProviderEffectRef $effect): ?ProviderEffectStatus
    {
        try {
            $app = ApplicationLocator::current();
            $actor = $app->make(DataScopeFactory::class)->forCurrentPrincipal($app->make(TenantContextResolver::class)->current());

            if ($actor->accountId !== $effect->accountId || $actor->accountIncarnation !== $effect->accountIncarnation || $actor->workspaceId !== $effect->workspaceId) {
                throw new ScopeContextMismatch('Provider journal access denied.');
            }
        } catch (ScopeContextMismatch|CentralContextException $denied) {
            ($this->recoveryAuthority() ?? throw $denied)->authorize($effect);
            $actor = null;
        }
        $row = $this->providerRows($effect)->first();

        if ($row === null) {
            return null;
        }
        $data = get_object_vars($row);
        $scope = json_decode($this->text($data, 'scope'), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($scope)) {
            throw new ScopeAuthorityUnavailable('Invalid provider scope.');
        }
        /** @var array<string,mixed> $scope */
        $ticket = new AdmissionTicket($this->text($data, 'ticket_id'), $this->codec->decode($scope),
            $this->number($data, 'control_revision'), new OperationKey($this->text($data, 'operation_id'),
                $this->number($data, 'attempt'), $this->text($data, 'effect_key'), new DateTimeImmutable($this->text($data, 'deadline'))), AdmissionKind::ProviderSend);

        if ($actor !== null && get_object_vars($actor) !== get_object_vars($ticket->scope)) {
            $recovery = $this->recoveryAuthority();

            if ($recovery === null) {
                return null;
            }
            $recovery->authorize($effect);
        }
        $observation = $row->observation_kind === null ? null : new ProviderObservation(
            ProviderObservationKind::from($this->text($data, 'observation_kind')), $this->text($data, 'observation_ref'), $this->text($data, 'observer'));

        return new ProviderEffectStatus($effect, $ticket, $observation, $this->text($data, 'state'), $this->text($data, 'owner_ref'), $this->text($data, 'claim_token'), $this->text($data, 'effect_digest'),
            $row->first_provider_ref === null ? null : $this->text($data, 'first_provider_ref'));
    }

    public function observe(AdmissionTicket $ticket, ProviderObservation $observation): void
    {
        $this->authorizeProvider($ticket);
        $control = $this->independent();
        $control->transaction(function () use ($control, $ticket, $observation): void {
            $this->route($ticket->scope->accountId)->lockForUpdate()->first();
            $query = $this->ticketRow($control, $ticket);
            $row = $query->lockForUpdate()->first();
            $this->verifyTicket($ticket, $row);

            if ($row === null || $row->provider_identity === null) {
                throw new ScopeContextMismatch('Provider observation requires a reserved effect.');
            }
            $kind = $observation->kind->value;

            $entry = ['ticket_id' => $ticket->id, 'kind' => $kind, 'reference' => $observation->reference, 'observer' => $observation->observer];
            $journal = $control->table('corex_provider_observations');

            if (! (clone $journal)->where($entry)->exists()) {
                $journal->insert([...$entry, 'id' => (string) Str::uuid7(), 'observed_at' => $this->clock->now()]);
            }

            $coordinateKinds = $this->singleInstanceReference($row->single_instance_reference)
                ? [ProviderObservationKind::Accepted->value, ProviderObservationKind::Confirmed->value]
                : [ProviderObservationKind::Accepted->value];
            $firstProviderRef = $row->first_provider_ref;

            if ($firstProviderRef === null && in_array($kind, $coordinateKinds, true)) {
                $firstProviderRef = $observation->reference;
                $query->update(['first_provider_ref' => $firstProviderRef]);
            }

            if (in_array($row->state, ['conflicting', 'isolated'], true)) {
                return;
            }
            $conflict = $firstProviderRef !== null && (clone $journal)->where('ticket_id', $ticket->id)
                ->whereIn('kind', $coordinateKinds)->where('reference', '<>', $firstProviderRef)->exists();
            $advance = $row->observation_kind === null || ($row->observation_kind === ProviderObservationKind::Unknown->value && $observation->kind !== ProviderObservationKind::Unknown)
                || ($row->observation_kind === ProviderObservationKind::Accepted->value && in_array($observation->kind, [ProviderObservationKind::Confirmed, ProviderObservationKind::Permanent], true));

            if (! $advance && $row->observation_kind !== $kind && $observation->kind !== ProviderObservationKind::Unknown
                && ! ($observation->kind === ProviderObservationKind::Accepted && in_array($row->observation_kind, ['confirmed', 'permanent'], true))) {
                $conflict = true;
            }

            if ($conflict) {
                $query->update(['state' => 'conflicting', 'observation_kind' => ProviderObservationKind::Unknown->value,
                    'observation_ref' => 'conflicting-provider-evidence', 'observer' => $observation->observer]);
            } elseif ($advance) {
                $query->update(['observation_kind' => $kind, 'observation_ref' => $observation->reference, 'observer' => $observation->observer]);
            }
        });
    }

    /** An active verified sender, or an independently authenticated recovery operator. */
    private function authorizeProvider(AdmissionTicket $ticket): void
    {
        $row = $this->ticketRow($this->connection(), $ticket)->first();
        $this->verifyTicket($ticket, $row);

        if ($ticket->kind !== AdmissionKind::ProviderSend || $row === null || ! is_string($row->provider_owner)
            || ! is_string($row->provider_namespace) || ! is_string($row->effect_key)) {
            throw new ScopeContextMismatch('Provider journal operation requires a reserved effect.');
        }

        try {
            ApplicationLocator::current()->make(CurrentWorkScope::class)->assert($ticket->scope);
        } catch (ScopeContextMismatch|CentralContextException $denied) {
            ($this->recoveryAuthority() ?? throw $denied)->authorize(new ProviderEffectRef($ticket->scope->accountId,
                $ticket->scope->accountIncarnation, $ticket->scope->workspaceId, $row->provider_owner, $row->provider_namespace, $row->effect_key));
        }
    }

    private function singleInstanceReference(mixed $stored): bool
    {
        return match ($stored) {
            true, 1, '1', 't' => true,
            false, 0, '0', 'f' => false,
            default => throw new ScopeAuthorityUnavailable('Invalid provider reference policy.'),
        };
    }

    private function recoveryAuthority(): ?ProviderRecoveryAuthority
    {
        $app = ApplicationLocator::current();

        return $app->bound(ProviderRecoveryAuthority::class) ? $app->make(ProviderRecoveryAuthority::class) : null;
    }

    private function ticketRow(Connection $control, AdmissionTicket $ticket): Builder
    {
        return $control->table('corex_scope_tickets')->where('ticket_id', $ticket->id)
            ->where('deployment_id', $this->deployment())->where('account_id', $ticket->scope->accountId)
            ->where('control_revision', $ticket->controlRevision)->where('kind', $ticket->kind->value)
            ->where('operation_id', $ticket->key->id)->where('attempt', $ticket->key->attempt);
    }

    private function verifyTicket(AdmissionTicket $ticket, ?object $row): void
    {
        $data = $row === null ? [] : get_object_vars($row);
        $deadline = is_string($data['deadline'] ?? null) ? new DateTimeImmutable($data['deadline']) : null;

        if ($deadline?->format('U.u') !== $ticket->key->deadline?->format('U.u') || ! is_string($data['scope'] ?? null) || json_decode($data['scope'], true, flags: JSON_THROW_ON_ERROR) !== $this->codec->encode($ticket->scope)
            || ($data['effect_key'] ?? null) !== $ticket->key->effectKey) {
            throw new ScopeContextMismatch('Admission ticket is unknown.');
        }
    }

    public function settle(AdmissionTicket $ticket, AttemptEvidence $evidence): void
    {
        if ($ticket->kind === AdmissionKind::ProviderSend) {
            $this->authorizeProvider($ticket);
        }
        $control = $this->independent();
        $control->transaction(function () use ($ticket, $evidence, $control): void {
            $query = $this->ticketRow($control, $ticket);
            $row = $query->lockForUpdate()->first();
            $this->verifyTicket($ticket, $row);

            if ($row === null) {
                throw new ScopeContextMismatch('Admission ticket is unknown.');
            }

            if ($row->provider_identity !== null && (
                ! in_array($evidence->outcome, ['unknown', 'reconciled', 'isolated'], true)
                || ($evidence->outcome === 'reconciled' && in_array($row->observation_kind, [null, 'unknown'], true))
            )) {
                throw new ScopeContextMismatch('Provider settlement requires definitive evidence.');
            }

            if ($row->provider_identity !== null && $evidence->outcome === 'isolated') {
                $data = get_object_vars($row);
                ($this->recoveryAuthority() ?? throw new ScopeContextMismatch('Operator isolation requires independent authorization.'))->authorize(
                    new ProviderEffectRef($ticket->scope->accountId, $ticket->scope->accountIncarnation, $ticket->scope->workspaceId,
                        $this->text($data, 'provider_owner'), $this->text($data, 'provider_namespace'), $this->text($data, 'effect_key')),
                );
            }

            if ($row->state === $evidence->outcome && $row->evidence === $evidence->reference) {
                return;
            }

            if ($row->state !== 'outstanding' && ! ($row->state === 'conflicting' && $evidence->outcome === 'isolated') && ! ($row->state === 'unknown' && in_array($evidence->outcome, ['reconciled', 'isolated'], true))) {
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
            $this->assertDrained($accountId);

            return ['mode' => 'closed'];
        });
    }

    private function assertDrained(string $accountId): void
    {
        if ($this->connection()->table('corex_scope_tickets')->where('deployment_id', $this->deployment())
            ->where('account_id', $accountId)->whereIn('state', ['outstanding', 'unknown', 'conflicting'])->exists()) {
            throw new ScopeContextMismatch('Outstanding work must be drained or reconciled.');
        }
    }

    /** @param Closure(PhysicalBinding):bool $verify Verifies the new physical owner, schema and isolation before reopening. */
    public function reopen(string $accountId, int $expectedRevision, PhysicalBinding $binding, Closure $verify): int
    {
        return $this->transition($accountId, $expectedRevision, 'closed', function (PhysicalBinding $old) use ($binding, $verify, $accountId): array {
            $this->assertDrained($accountId);
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
