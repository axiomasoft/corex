<?php

declare(strict_types=1);

namespace CoreX\Crm\Models;

use Brick\Money\Money;
use CoreX\Crm\Enums\DealStatus;
use CoreX\Models\TenantModel;
use CoreX\Money\MoneyCast;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property string $id
 * @property string|null $tenant_id
 * @property string $pipeline_id
 * @property string $stage_id
 * @property string $title
 * @property Money|null $amount
 * @property DealStatus $status
 * @property string|null $owner_id
 * @property int $position
 * @property string|null $contact_id
 * @property string|null $company_id
 * @property array<string, mixed>|null $custom_fields
 */
final class Deal extends TenantModel
{
    protected $table = 'crm_deals';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'pipeline_id', 'stage_id', 'title', 'amount', 'status', 'owner_id', 'position', 'contact_id', 'company_id', 'custom_fields'];

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

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return MorphMany<Activity, $this> */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class.':amount_minor,currency',
            'status' => DealStatus::class,
            'position' => 'integer',
            'custom_fields' => 'array',
        ];
    }
}
