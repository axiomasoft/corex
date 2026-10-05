<?php

declare(strict_types=1);

namespace CoreX\Tenancy\DomainTrust\Internal\Models;

use Carbon\CarbonImmutable;
use CoreX\Tenancy\Account\Laravel\AccountRelations;
use CoreX\Tenancy\Laravel\Support\ResolvesTenancyOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * `root_domains` — per-account host registry (central connection, D14).
 * Host resolution uses the central host registry directly.
 *
 * @property string $id
 * @property string $account_id
 * @property string $host
 * @property string $type
 * @property bool $is_primary
 * @property ?string $verification_token
 * @property ?CarbonImmutable $verified_at
 * @property int $verification_generation
 * @property string $tls_status
 * @property ?string $ca_used
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property ?CarbonImmutable $deleted_at
 */
final class Domain extends Model
{
    use ResolvesTenancyOptions;

    protected $table = 'root_domains';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'verified_at' => 'immutable_datetime', 'verification_generation' => 'integer', 'deleted_at' => 'immutable_datetime'];
    }

    /**
     * @return BelongsTo<covariant \Illuminate\Database\Eloquent\Model, $this>
     */
    public function account(): BelongsTo
    {
        return AccountRelations::belongsTo($this, 'account_id', null, 'account');
    }

    #[Override]
    public function getConnectionName(): string
    {
        return parent::getConnectionName() ?? $this->tenancyOptions()->centralConnection;
    }
}
