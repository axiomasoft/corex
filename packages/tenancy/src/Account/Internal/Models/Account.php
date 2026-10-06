<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Account\Internal\Models;

use Carbon\CarbonImmutable;
use CoreX\Tenancy\DomainTrust\Laravel\DomainRelations;
use CoreX\Tenancy\Laravel\Support\ResolvesTenancyOptions;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * `root_accounts` is a physical DB-per-account tenant on the central connection.
 * Only explicit typed columns are writable through this model.
 * Signup claim and cleanup fields are written only by their SQL protocols.
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
final class Account extends Model
{
    use HasUuids;
    use ResolvesTenancyOptions;

    protected $table = 'root_accounts';

    protected $fillable = ['id', 'slug', 'name', 'cluster_id', 'db_name', 'status', 'owner_identity_id', 'vertical_code', 'template_version', 'embedding_model', 'embedding_dim', 'locale', 'trial_ends_at', 'suspended_at', 'grace_until', 'purge_after', 'deleted_at'];

    #[Override]
    public function getConnectionName(): string
    {
        return parent::getConnectionName() ?? $this->tenancyOptions()->centralConnection;
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['template_version' => 'integer', 'embedding_dim' => 'integer', 'trial_ends_at' => 'immutable_datetime', 'suspended_at' => 'immutable_datetime', 'grace_until' => 'immutable_datetime', 'purge_after' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime'];
    }

    /**
     * @return HasMany<covariant \Illuminate\Database\Eloquent\Model, $this>
     */
    public function domains(): HasMany
    {
        return DomainRelations::hasMany($this, 'account_id');
    }
}
