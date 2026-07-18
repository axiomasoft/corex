<?php

declare(strict_types=1);

namespace Flex\Crm\Actions;

use Flex\Crm\Enums\DealStatus;
use Flex\Crm\Events\DealMovedToStage;
use Flex\Crm\Models\Deal;
use Flex\Crm\Models\Stage;
use Illuminate\Support\Facades\DB;

/**
 * Moves a deal to a stage and derives its status from the stage's terminal
 * flags (won/lost), otherwise keeps it open.
 */
final class MoveDealToStage
{
    public function handle(Deal $deal, Stage $stage): Deal
    {
        return DB::transaction(function () use ($deal, $stage): Deal {
            $deal->stage()->associate($stage);
            $deal->status = match (true) {
                $stage->is_won => DealStatus::Won,
                $stage->is_lost => DealStatus::Lost,
                default => DealStatus::Open,
            };
            $deal->save();

            DealMovedToStage::dispatch($deal, $stage);

            return $deal;
        });
    }
}
