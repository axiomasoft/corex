<?php

declare(strict_types=1);

namespace Flex\Crm\Models;

use Carbon\CarbonImmutable;
use CoreX\Models\BaseModel;
use Flex\Crm\Models\Concerns\HasCrmContext;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Override;

/**
 * @property string $id
 * @property string|null $name_first
 * @property string|null $name_last
 * @property string|null $name_middle
 * @property string $display_name
 * @property string|int|null $workspace_id
 * @property string|int|null $source_id
 * @property list<array{value: string, norm: string, type: ?string, is_primary: bool}> $phones
 * @property list<array{value: string, norm: string, type: ?string, is_primary: bool}> $emails
 * @property array{source: string, id: string, name: string} $created_by
 * @property string|null $search
 * @property CarbonImmutable|null $birthday
 * @property string|null $job_title
 * @property string|null $owner_id
 */
final class Contact extends BaseModel
{
    use HasCrmContext;

    protected $table = 'crm_contacts';

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'name_first', 'name_last', 'name_middle', 'job_title', 'birthday', 'phones', 'emails', 'source_id', 'owner_id'];

    /** @return BelongsToMany<Company, $this, ContactCompany> */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(related: Company::class, table: (new ContactCompany)->getTable())
            ->using(ContactCompany::class)->withPivot(['id', 'label', 'is_primary'])->withTimestamps();
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
    #[Override]
    protected function casts(): array
    {
        return ['phones' => 'array', 'emails' => 'array', 'created_by' => 'array', 'birthday' => 'immutable_date'];
    }
}
