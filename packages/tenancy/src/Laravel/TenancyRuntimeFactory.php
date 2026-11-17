<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Laravel;

use Closure;
use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Context\Contracts\TenancyManager;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Tenancy\Workspace\Internal\Models\Workspace;
use Illuminate\Container\Container;

/**
 * Builds the tenant runtime pieces whose construction is more than one expression, so the registrar
 * keeps single-return factory closures. Nothing here captures an application instance that a
 * resident worker could outlive: the hooks resolve the container when they run.
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class TenancyRuntimeFactory
{
    /**
     * The workspace of a queued job, looked up in the account database after it was entered.
     *
     * @return Closure(string): ?WorkspaceRef
     */
    public static function workspaceResolver(): Closure
    {
        return static function (string $id): ?WorkspaceRef {
            $row = Workspace::query()->find($id);

            return $row === null ? null : new WorkspaceRef(
                id: $row->id,
                slug: $row->slug,
                type: $row->type,
                path: $row->path,
                parentId: $row->parent_id,
            );
        };
    }

    /**
     * Payload hook of every queued job: stamps the active account and workspace.
     *
     * @return Closure(string, ?string, array<array-key, mixed>): array<string, mixed>
     */
    public static function payloadHook(): Closure
    {
        return static function (string $connection, ?string $queue, array $payload): array {
            $context = Container::getInstance()->make(TenancyManager::class)->context();

            return $context === null ? [] : ['corex_tenant' => [
                'account' => $context->account->id,
                'workspace' => $context->workspace?->id,
            ]];
        };
    }
}
