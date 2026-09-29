<?php

declare(strict_types=1);

namespace Flex\Crm\Deal\Actions;

use Brick\Money\Money;
use Flex\Crm\Context\ContextGuard;
use Flex\Crm\Deal\Enums\DealStatus;
use Flex\Crm\Deal\Events\DealCreated;
use Flex\Crm\Deal\Models\Deal;
use Flex\Crm\Deal\Models\Pipeline;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Creates a deal at the pipeline's first stage. The transaction is the
 * use-case boundary.
 */
final class CreateDeal
{
    public function __construct(private readonly ContextGuard $guard) {}

    public function handle(Pipeline $pipeline, string $title, ?Money $amount = null, ?string $ownerId = null): Deal
    {
        $ctx = $this->guard->current();

        if ($pipeline->getConnection()->getName() !== $ctx->connectionName) {
            throw new AuthorizationException('Pipeline connection differs from the current account.');
        }

        return $this->guard->connection($ctx)->transaction(function () use ($pipeline, $title, $amount, $ownerId, $ctx): Deal {
            $this->guard->current(lock: true);
            $pipeline = Pipeline::on($ctx->connectionName)->whereKey($pipeline->getKey())->sharedLock()->firstOrFail();

            if ($pipeline->entity_type !== 'crm.deal' || ($pipeline->workspace_id !== null && $pipeline->workspace_id !== $ctx->tenant->workspace?->id)) {
                throw new AuthorizationException('Pipeline is not available for deals in this workspace.');
            }
            $stage = $pipeline->stages()->first();

            if ($stage === null) {
                throw new RuntimeException('Воронка без стадий: добавьте стадию перед созданием сделки.');
            }

            $deal = Deal::on($ctx->connectionName)->create([
                'workspace_id' => $this->guard->workspace(ctx: $ctx, accountLevel: false),
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'title' => $title,
                'status' => DealStatus::from($stage->semantic),
                'won_at' => $stage->semantic === 'won' ? now() : null,
                'lost_at' => $stage->semantic === 'lost' ? now() : null,
                'stage_entered_at' => now(),
                'owner_id' => $ownerId,
                ...($amount === null ? ['currency' => 'RUB'] : ['amount' => $amount]),
            ]);

            if (! $deal->exists) {
                throw ValidationException::withMessages(['deal' => 'Deal creation was vetoed.']);
            }
            foreach ($stage->required_fields ?? [] as $field) {
                if ($deal->getAttribute($field) === null || $deal->getAttribute($field) === '') {
                    throw ValidationException::withMessages(['stage_id' => 'Required stage fields are missing.']);
                }
            }

            DealCreated::dispatch($deal);

            return $deal;
        });
    }
}
