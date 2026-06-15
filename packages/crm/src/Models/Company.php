<?php

declare(strict_types=1);

namespace CoreX\Crm\Models;

use CoreX\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $inn
 * @property string|null $kpp
 * @property string|null $website
 * @property string|null $owner_id
 * @property array<string, mixed>|null $custom_fields
 */
final class Company extends TenantModel
{
    protected $table = 'crm_companies';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'name', 'legal_name', 'inn', 'kpp', 'website', 'owner_id', 'custom_fields'];

    /** @return HasMany<Contact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
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
