<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Workspace\Internal\Models;

use Carbon\CarbonImmutable;
use CoreX\Tenancy\Identity\Laravel\UserRelations;
use CoreX\Tenancy\Workspace\Exceptions\InvitationNotAcceptableException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * `wsp_invitations` — invitation into a workspace (or the account, when
 * `workspace_id` is null). `role_id` is a soft-ref with no FK (D21): the
 * role is only resolved/applied on acceptance, application-side. No soft
 * deletes — the table carries no `deleted_at` (B-11 §2.2).
 *
 * @property string $id
 * @property string $email
 * @property ?string $workspace_id
 * @property ?string $role_id
 * @property string $invited_by
 * @property string $token_hash
 * @property string $status
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $accepted_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class Invitation extends Model
{
    use HasUuids;

    protected $table = 'wsp_invitations';

    /**
     * Mirrors the DB defaults (`status` DEFAULT 'pending'): a
     * non-incrementing model's create() does not re-fetch the inserted row,
     * so without this the in-memory attribute would stay unset even though
     * the DB column already holds 'pending'.
     *
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending'];

    /** @var list<string> */
    protected $fillable = ['email', 'workspace_id', 'role_id', 'invited_by', 'token_hash', 'status', 'expires_at', 'accepted_at'];

    /** @var list<string> */
    protected $hidden = ['token_hash'];

    /** @return BelongsTo<Workspace, $this> */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** @return BelongsTo<covariant \Illuminate\Database\Eloquent\Model, $this> */
    public function invitedBy(): BelongsTo
    {
        return UserRelations::belongsTo($this, 'invited_by', null, 'invitedBy');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    /**
     * Accept the invitation as `$userId` (AC-19): revoked/already-accepted/
     * (persisted-or-lazily) expired → not acceptable; `$usersMax` (from the
     * account's `limits`) is checked against the workspace's active member
     * count before creating the membership. Role assignment lands with
     * axiomasoft/corex-access's `aut_role_assignments` (P2.8, Scope Excluded here) —
     * `role_id` is only carried on the invitation row.
     */
    public function accept(string $userId, int $usersMax): ?WorkspaceMember
    {
        return $this->getConnection()->transaction(function () use ($userId, $usersMax): ?WorkspaceMember {
            $query = $this->newQuery()->whereKey($this->getKey());
            $query->getQuery()->lockForUpdate();
            $invitation = $query->firstOrFail();
            $this->setRawAttributes($invitation->getAttributes(), true);

            if ($this->status !== 'pending' || $this->expires_at->isPast()) {
                throw new InvitationNotAcceptableException("Invitation [{$this->id}] is not acceptable (status={$this->status}).");
            }

            if ($this->workspace_id === null) {
                $this->update(['status' => 'accepted', 'accepted_at' => $this->freshTimestamp()]);

                return null;
            }
            // All invitations for this workspace compete for the same quota resource.
            $workspaceQuery = Workspace::on($this->getConnectionName())->whereKey($this->workspace_id);
            $workspaceQuery->getQuery()->lockForUpdate();
            $workspaceQuery->firstOrFail();
            $currentMembers = WorkspaceMember::on($this->getConnectionName())->toBase()->where('workspace_id', $this->workspace_id)->where('status', 'active')->count();

            if ($currentMembers >= $usersMax) {
                throw new InvitationNotAcceptableException("Workspace [{$this->workspace_id}] has reached its users_max limit ({$usersMax}).");
            }
            $member = WorkspaceMember::on($this->getConnectionName())->create(['workspace_id' => $this->workspace_id, 'user_id' => $userId, 'status' => 'active']);
            $this->update(['status' => 'accepted', 'accepted_at' => $this->freshTimestamp()]);

            return $member;
        });
    }
}
