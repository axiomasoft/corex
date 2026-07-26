<?php

declare(strict_types=1);

namespace CoreX\Audit;

use CoreX\Enums\ActorType;

/**
 * Actor/request snapshot for an audit entry built outside a queue worker
 * (context: ip/user_agent/request_id/module). Real agent/api_key actor
 * wiring lands with entity-scoped auth — today this only distinguishes an
 * authenticated user from 'system'.
 *
 * @internal spec: B-10 §3.1, P2, OQ-7
 */
final class CurrentActor
{
    public static function type(): ActorType
    {
        return auth()->check() ? ActorType::User : ActorType::System;
    }

    public static function id(): ?string
    {
        $user = auth()->user();

        return $user !== null ? (string) $user->getAuthIdentifier() : null;
    }

    /** @return array<string, mixed> */
    public static function context(): array
    {
        if (app()->runningInConsole() || ! app()->bound('request')) {
            return [];
        }

        $request = request();

        return array_filter([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->header('X-Request-Id'),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
