<?php

declare(strict_types=1);

namespace Flex\Crm\Models;

use CoreX\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $job_title
 * @property string|null $company_id
 * @property string|null $owner_id
 * @property array<string, mixed>|null $custom_fields
 * @property string|null $merged_into_id
 */
final class Contact extends TenantModel
{
    protected $table = 'crm_contacts';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'first_name', 'last_name', 'email', 'phone', 'job_title', 'company_id', 'owner_id', 'custom_fields', 'merged_into_id'];

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** @return MorphMany<Activity, $this> */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['custom_fields' => 'array'];
    }
}
