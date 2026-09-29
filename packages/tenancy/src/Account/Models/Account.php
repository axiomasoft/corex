<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Models;

use Carbon\CarbonImmutable;
use CoreX\Tenancy\DomainTrust\Models\Domain;
use CoreX\Tenancy\Provisioning\StanclTenantDatabaseProvisioner;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\InvalidatesResolverCache;
use Stancl\Tenancy\Database\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Models\Tenant;
use Stancl\Tenancy\Events\CreatingTenant;
use Stancl\Tenancy\Events\DeletingTenant;
use Stancl\Tenancy\Events\SavingTenant;
use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Events\TenantSaved;
use Stancl\Tenancy\Events\TenantUpdated;
use Stancl\Tenancy\Events\UpdatingTenant;

/**
 * `root_accounts` — physical DB-per-account tenant (B-11 §3.1, D14). Typed
 * columns are declared via {@see getCustomColumns()}; everything else the
 * base `Tenant` model would otherwise persist falls into the `data` jsonb
 * VirtualColumn (stancl/virtualcolumn).
 *
 * `internalPrefix()` is overridden to '' (default is `tenancy_`) because
 * `db_name` is a plain, unprefixed column in the frozen DB_SCHEMA contract —
 * stancl's HasDatabase/DatabaseConfig read/write it via `getInternal('db_name')`.
 *
 * @property string $id
 * @property ?string $slug
 * @property ?string $name
 * @property string $cluster_id
 * @property string $db_name
 * @property string $status
 * @property ?string $owner_identity_id
 * @property ?string $vertical_code
 * @property int $template_version
 * @property string $embedding_model
 * @property int $embedding_dim
 * @property string $locale
 * @property ?CarbonImmutable $trial_ends_at
 * @property ?CarbonImmutable $suspended_at
 * @property ?CarbonImmutable $grace_until
 * @property ?CarbonImmutable $purge_after
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property ?CarbonImmutable $deleted_at
 */
final class Account extends Tenant implements TenantWithDatabase
{
    use HasDatabase;

    // Base Tenant already mixes this trait in (class_uses_recursive picks it
    // up), so cache invalidation on save/delete is active either way —
    // restated here so it's visible on this model without tracing the
    // vendor base class (P2.10, D119).
    use InvalidatesResolverCache;

    protected $table = 'root_accounts';

    /**
     * `created` is deliberately NOT mapped to `Events\TenantCreated` here
     * (unlike the base `Tenant` model): a `root_accounts` row can exist long
     * before real provisioning starts (e.g. the P2.4 pending pool) —
     * provisioning is triggered explicitly by
     * {@see StanclTenantDatabaseProvisioner::provision()},
     * not implicitly by row-creation.
     */
    protected $dispatchesEvents = [
        'saving' => SavingTenant::class,
        'saved' => TenantSaved::class,
        'creating' => CreatingTenant::class,
        'updating' => UpdatingTenant::class,
        'updated' => TenantUpdated::class,
        'deleting' => DeletingTenant::class,
        'deleted' => TenantDeleted::class,
    ];

    #[Override]
    public static function internalPrefix(): string
    {
        return '';
    }

    /**
     * @return list<string>
     */
    #[Override]
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'slug',
            'name',
            'cluster_id',
            'db_name',
            'status',
            'owner_identity_id',
            'vertical_code',
            'template_version',
            'embedding_model',
            'embedding_dim',
            'locale',
            'trial_ends_at',
            'suspended_at',
            'grace_until',
            'purge_after',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'account_id');
    }
}
