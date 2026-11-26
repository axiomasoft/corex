<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Impersonation\Internal\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Packman\Attributes\Internal;

/**
 * `root_impersonation_grants` — thin Eloquent read model. The registrar
 * supplies the configured central connection when resolving an instance;
 * callers of Eloquent's static API must select that connection explicitly.
 * The one-time atomic claim itself is a query-builder `UPDATE` in
 * {@see CoreX\Tenancy\Impersonation\Internal\DatabaseImpersonationGrantStore},
 * not an Eloquent write — this model exists for the L2 exempt-list
 * (`ImpersonatedModelWriteGuard`) and read access after the claim.
 *
 * @property string $id
 * @property string $staff_identity_id
 * @property string $account_id
 * @property ?string $target_user_id
 * @property string $reason
 * @property ?string $approved_by
 * @property CarbonImmutable $expires_at
 * @property ?CarbonImmutable $used_at
 * @property ?CarbonImmutable $revoked_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Internal]
final class ImpersonationGrant extends Model
{
    protected $table = 'root_impersonation_grants';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
        ];
    }
}
