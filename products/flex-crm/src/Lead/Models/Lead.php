<?php

declare(strict_types=1);

namespace Flex\Crm\Lead\Models;

use CoreX\Database\Laravel\BaseModel;
use Flex\Crm\Context\Models\Concerns\HasActivities;
use Flex\Crm\Context\Models\Concerns\HasCrmContext;
use Flex\Crm\Lead\Enums\LeadStatus;
use Illuminate\Support\Carbon;
use Packman\Attributes\Internal;

/**
 * @property string $id
 * @property string $title
 * @property int|null $amount
 * @property string $currency
 * @property string|null $contact_name
 * @property string|null $source_id
 * @property string|null $workspace_id
 * @property array<string, string> $created_by
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $company_name
 * @property LeadStatus $status
 * @property string|null $owner_id
 * @property string|null $converted_contact_id
 * @property string|null $converted_company_id
 * @property string|null $converted_deal_id
 * @property Carbon|null $converted_at
 */
#[Internal]
final class Lead extends BaseModel
{
    use HasActivities;
    use HasCrmContext;

    protected $table = 'crm_leads';

    /** @var list<string> */
    protected $fillable = [
        'workspace_id', 'title', 'contact_name', 'email', 'phone', 'company_name', 'source_id', 'status', 'owner_id',
        'pipeline_id', 'stage_id', 'amount', 'currency', 'utm', 'lost_reason_id', 'position',
        'converted_contact_id', 'converted_company_id', 'converted_deal_id', 'converted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'amount' => 'integer',
            'utm' => 'array',
            'position' => 'decimal:10',
            'created_by' => 'array',
            'converted_at' => 'datetime',
        ];
    }
}
