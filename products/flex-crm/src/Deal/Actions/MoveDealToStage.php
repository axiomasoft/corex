<?php

declare(strict_types=1);

namespace Flex\Crm\Deal\Actions;

use Flex\Crm\Context\ContextGuard;
use Flex\Crm\Deal\Enums\DealStatus;
use Flex\Crm\Deal\Events\DealMovedToStage;
use Flex\Crm\Deal\Models\Deal;
use Flex\Crm\Deal\Models\Stage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Moves a deal to a stage and derives its status from the stage's terminal
 * flags (won/lost), otherwise keeps it open.
 */
final class MoveDealToStage
{
    public function __construct(private readonly ContextGuard $guard) {}

    public function handle(Deal $deal, Stage $stage): Deal
    {
        $ctx = $this->guard->current();

        if ($deal->getConnection()->getName() !== $ctx->connectionName || $stage->getConnection()->getName() !== $ctx->connectionName) {
            throw new AuthorizationException('Stage transition requires the current account connection.');
        }

        return $deal->getConnection()->transaction(function () use ($deal, $stage): Deal {
            $this->guard->current(lock: true);
            $fresh = $deal->newQuery()->whereKey($deal->getKey())->tap(static fn ($query) => $query->getQuery()->lockForUpdate())->firstOrFail();
            $stage = Stage::on($deal->getConnectionName())->whereKey($stage->getKey())->tap(static fn ($query) => $query->getQuery()->lockForUpdate())->firstOrFail();

            if ($fresh->pipeline_id !== $stage->pipeline_id) {
                throw ValidationException::withMessages(['stage_id' => 'Stage must belong to the deal pipeline.']);
            }
            $deal->setRawAttributes($fresh->getAttributes(), true);
            $deal->stage()->associate($stage);
            foreach ($stage->required_fields ?? [] as $field) {
                if ($deal->getAttribute($field) === null || $deal->getAttribute($field) === '') {
                    throw ValidationException::withMessages(['stage_id' => 'Required stage fields are missing.']);
                }
            }
            $deal->status = DealStatus::from($stage->semantic);
            $deal->won_at = $stage->semantic === 'won' ? now() : null;
            $deal->lost_at = $stage->semantic === 'lost' ? now() : null;
            $deal->stage_entered_at = now();

            if (! $deal->save()) {
                throw ValidationException::withMessages(['deal' => 'Stage transition was vetoed.']);
            }

            DealMovedToStage::dispatch($deal, $stage);

            return $deal;
        });
    }
}
