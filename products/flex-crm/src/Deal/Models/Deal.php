<?php

declare(strict_types=1);

namespace Flex\Crm\Deal\Models;

use Brick\Money\Money;
use CoreX\Database\Laravel\BaseModel;
use Flex\Crm\Company\Models\Company;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Context\Models\Concerns\HasActivities;
use Flex\Crm\Context\Models\Concerns\HasCrmContext;
use Flex\Crm\Deal\Casts\DealAmount;
use Flex\Crm\Deal\Enums\DealStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $pipeline_id
 * @property string $stage_id
 * @property string $title
 * @property Money|null $amount
 * @property DealStatus $status
 * @property string|null $owner_id
 * @property string $position
 * @property string|null $workspace_id
 * @property string|null $source_id
 * @property Carbon|null $won_at
 * @property Carbon|null $lost_at
 * @property Carbon $stage_entered_at
 * @property array<string, string> $created_by
 * @property string|null $company_id
 */
final class Deal extends BaseModel
{
    use HasActivities;
    use HasCrmContext;

    protected $table = 'crm_deals';

    /** @var list<string> */
    protected $fillable = ['workspace_id', 'pipeline_id', 'stage_id', 'title', 'amount', 'currency', 'status', 'owner_id', 'position', 'company_id', 'source_id', 'utm', 'lost_reason_id', 'close_date', 'won_at', 'lost_at', 'primary_quote_doc_id', 'forecast_category', 'forecast_category_is_manual', 'stage_entered_at'];

    /** @return BelongsTo<Pipeline, $this> */
    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsToMany<Contact, $this> */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'crm_deal_contacts')->withPivot(['id', 'label', 'is_primary'])->withTimestamps();
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => DealAmount::class,
            'status' => DealStatus::class,
            'position' => 'decimal:10',
            'utm' => 'array',
            'created_by' => 'array',
            'close_date' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
            'stage_entered_at' => 'datetime',
            'forecast_category_is_manual' => 'boolean',
        ];
    }
}
