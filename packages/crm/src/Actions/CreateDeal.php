<?php

declare(strict_types=1);

namespace Flex\Crm\Actions;

use Brick\Money\Money;
use Flex\Crm\Enums\DealStatus;
use Flex\Crm\Events\DealCreated;
use Flex\Crm\Models\Deal;
use Flex\Crm\Models\Pipeline;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Creates a deal at the pipeline's first stage. The transaction is the
 * use-case boundary.
 */
final class CreateDeal
{
    public function handle(Pipeline $pipeline, string $title, ?Money $amount = null, ?string $ownerId = null): Deal
    {
        return DB::transaction(function () use ($pipeline, $title, $amount, $ownerId): Deal {
            $stage = $pipeline->stages()->first();

            if ($stage === null) {
                throw new RuntimeException('Воронка без стадий: добавьте стадию перед созданием сделки.');
            }

            $deal = Deal::query()->create([
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'title' => $title,
                'status' => DealStatus::Open,
                'owner_id' => $ownerId,
                'amount' => $amount,
            ]);

            DealCreated::dispatch($deal);

            return $deal;
        });
    }
}
