<?php

declare(strict_types=1);

namespace Flex\Crm\Company\Models;

use CoreX\Database\BaseModel;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Contact\Models\ContactCompany;
use Flex\Crm\Context\Models\Concerns\HasActivities;
use Flex\Crm\Context\Models\Concerns\HasCrmContext;
use Flex\Crm\Deal\Models\Deal;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property string $id
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $inn
 * @property string|null $kpp
 * @property string|null $domain
 * @property string|null $ogrn
 * @property string|int|null $workspace_id
 * @property string|int|null $source_id
 * @property list<array{value: string, norm: string, type: ?string, is_primary: bool}> $phones
 * @property list<array{value: string, norm: string, type: ?string, is_primary: bool}> $emails
 * @property array<string, mixed>|null $address
 * @property array<string, mixed>|null $bank_details
 * @property array{source: string, id: string, name: string} $created_by
 * @property string|null $search
 * @property string|null $owner_id
 */
final class Company extends BaseModel
{
    use HasActivities;
    use HasCrmContext;

    protected $table = 'crm_companies';

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'name', 'legal_name', 'domain', 'inn', 'kpp', 'ogrn', 'phones', 'emails', 'address', 'bank_details', 'source_id', 'owner_id'];

    /** @return BelongsToMany<Contact, $this, ContactCompany> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(related: Contact::class, table: (new ContactCompany)->getTable())
            ->using(ContactCompany::class)->withPivot(['id', 'label', 'is_primary'])->withTimestamps();
    }

    /** @return HasMany<Deal, $this> */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /** @return array<string, string> */
    #[Override]
    protected function casts(): array
    {
        return ['phones' => 'array', 'emails' => 'array', 'address' => 'array', 'bank_details' => 'array', 'created_by' => 'array'];
    }
}
