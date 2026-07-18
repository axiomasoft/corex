<?php

declare(strict_types=1);

namespace Flex\Crm\Models;

use CoreX\Models\BaseModel;
use Flex\Crm\Enums\LeadSource;
use Flex\Crm\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company_name
 * @property LeadSource $source
 * @property LeadStatus $status
 * @property int|null $score
 * @property string|null $owner_id
 * @property array<string, mixed>|null $custom_fields
 * @property string|null $converted_contact_id
 * @property string|null $converted_company_id
 * @property string|null $converted_deal_id
 * @property Carbon|null $converted_at
 */
final class Lead extends BaseModel
{
    protected $table = 'crm_leads';

    /** @var list<string> */
    protected $fillable = [
        'name', 'email', 'phone', 'company_name', 'source', 'status', 'score', 'owner_id',
        'custom_fields', 'converted_contact_id', 'converted_company_id', 'converted_deal_id', 'converted_at',
    ];

    /** @return MorphMany<Activity, $this> */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'score' => 'integer',
            'custom_fields' => 'array',
            'converted_at' => 'datetime',
        ];
    }
}
