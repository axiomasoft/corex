<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Internal;

use CoreX\Tenancy\Impersonation\Contracts\ImpersonationGrantStore;
use CoreX\Tenancy\Impersonation\Data\ClaimedGrant;
use CoreX\Tenancy\Impersonation\Data\GrantClaim;
use DateTimeImmutable;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Query\Builder;
use RuntimeException;

/** @internal */
final readonly class DatabaseImpersonationGrantStore implements ImpersonationGrantStore
{
    public function __construct(private ConnectionResolverInterface $connections, private string $centralConnection) {}

    public function claim(GrantClaim $claim): ?ClaimedGrant
    {
        $central = $this->connections->connection($this->centralConnection);

        if ($central->transactionLevel() !== 0) {
            throw new RuntimeException('Impersonation claim forbids ambient transactions');
        }

        $affected = $central->table('root_impersonation_grants')
            ->where('id', $claim->grantId)
            ->where('staff_identity_id', $claim->staffIdentityId)
            ->where('account_id', $claim->accountId)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $claim->now)
            ->where('reason', '<>', '')
            ->whereRaw(sql: 'expires_at <= created_at + make_interval(mins => ?)', bindings: [$claim->profile->maxGrantMinutes()])
            ->when($claim->profile->requiresIndependentApproval(), static fn (Builder $query) => $query
                ->whereNotNull('approved_by')
                ->whereColumn('approved_by', '<>', 'staff_identity_id')
                ->whereExists(static function (Builder $approved): void {
                    $approved->selectRaw('1')->from('root_identities as approver')
                        ->whereColumn('approver.id', 'root_impersonation_grants.approved_by')
                        ->where('approver.is_staff', true);
                }))
            ->whereExists(static function (Builder $staff) use ($claim): void {
                $staff->selectRaw('1')->from('root_identities as claimant')
                    ->where('claimant.id', $claim->staffIdentityId)
                    ->where('claimant.is_staff', true);
            })
            ->update(['used_at' => $claim->now, 'updated_at' => $claim->now]);

        if ($affected !== 1) {
            return null;
        }

        $row = $central->table('root_impersonation_grants')->where('id', $claim->grantId)->first();

        if ($row === null || ! is_string($row->reason) || $row->reason === '' || ! is_string($row->expires_at)) {
            throw new RuntimeException('Claimed impersonation grant cannot be read');
        }

        return new ClaimedGrant(
            grantId: $claim->grantId,
            targetUserId: is_string($row->target_user_id) ? $row->target_user_id : null,
            reason: $row->reason,
            expiresAt: new DateTimeImmutable($row->expires_at),
        );
    }

    public function claimedReason(string $grantId): ?string
    {
        $reason = $this->connections->connection($this->centralConnection)
            ->table('root_impersonation_grants')
            ->where('id', $grantId)
            ->whereNotNull('used_at')
            ->value('reason');

        return is_string($reason) && $reason !== '' ? $reason : null;
    }
}
