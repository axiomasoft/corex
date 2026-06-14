<?php

declare(strict_types=1);

namespace CoreX\Crm\Models;

use Brick\Money\Money;
use CoreX\Crm\Enums\DealStatus;
use CoreX\Models\TenantModel;
use CoreX\Money\MoneyCast;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
 */
final class Deal extends TenantModel
{
    protected $table = 'crm_deals';

    /** @var list<string> */
    protected $fillable = ['tenant_id', 'pipeline_id', 'stage_id', 'title', 'amount', 'status', 'owner_id', 'position'];

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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class.':amount_minor,currency',
            'status' => DealStatus::class,
            'position' => 'integer',
        ];
    }
}
