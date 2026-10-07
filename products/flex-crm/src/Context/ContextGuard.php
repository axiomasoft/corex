<?php

declare(strict_types=1);

namespace Flex\Crm\Context;

use CoreX\Context\Contracts\AccountConnectionResolver;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Modules\Contracts\ModuleRegistry;
use Flex\Crm\Context\Contracts\CrmExecutionContext;
use Flex\Crm\Context\Data\ExecutionContext;
use Flex\Crm\Internal\Value;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** @internal Mandatory package admission; consumer adapters can only narrow it. */
final readonly class ContextGuard
{
    public function __construct(
        private CrmExecutionContext $execution,
        private TenantContextResolver $tenants,
        private AccountConnectionResolver $connections,
        private ModuleRegistry $modules,
    ) {}

    public function current(bool $lock = false): ExecutionContext
    {
        $ctx = $this->execution->current();
        $tenant = $this->tenants->current();

        if ($tenant->isBoxed() || $tenant->account->status !== 'active' || $tenant->account->id !== $ctx->tenant->account->id || $tenant->workspace?->id !== $ctx->tenant->workspace?->id) {
            throw new AuthorizationException('CRM requires the active physical account/workspace context.');
        }
        $db = $this->connection($ctx);
        $expected = $this->connections->resolve($tenant->account);
        foreach (['driver', 'host', 'port', 'database', 'username'] as $key) {
            if (Value::text($db->getConfig($key)) !== Value::text($expected->{$key})) {
                throw new AuthorizationException('CRM connection does not belong to the current account.');
            }
        }

        if ($db->getDriverName() === 'pgsql' && (($active = $db->selectOne('SELECT current_database() AS name')) === null || ! is_object($active) || Value::field($active, 'name') !== $expected->database)) {
            throw new AuthorizationException('CRM active PDO database differs from the account connection.');
        }

        if (! $this->modules->isActive('flex/crm', $tenant)) {
            throw new AuthorizationException('CRM is not enabled in the current account.');
        }

        if ($ctx->actor->source === 'user' && $ctx->actor->id !== (string) $ctx->principalId) {
            throw new AuthorizationException('CRM actor does not match the authenticated principal.');
        }
        $this->user(ctx: $ctx, id: $ctx->principalId, lock: $lock);

        if ($tenant->workspace === null && ! $ctx->allowAccountLevel) {
            throw new AuthorizationException('An explicit account-level capability is required without a workspace.');
        }

        return $ctx;
    }

    public function connection(ExecutionContext $ctx): Connection
    {
        return DB::connection($ctx->connectionName);
    }

    public function user(ExecutionContext $ctx, string|int $id, bool $lock = false): void
    {
        $this->id($id);
        $db = $this->connection($ctx);
        $user = $db->table('users')->where('id', $id)->where('is_active', true)->whereNull('deleted_at');

        if ($lock) {
            $user->sharedLock();
        }

        if ($user->first() === null) {
            throw new AuthorizationException('CRM principal/owner must be an active user in this account.');
        }

        if ($ctx->tenant->workspace !== null) {
            $workspace = $db->table('wsp_workspaces')->where('id', $ctx->tenant->workspace->id)->whereNull('deleted_at');
            $membership = $db->table('wsp_workspace_members')->where('workspace_id', $ctx->tenant->workspace->id)->where('user_id', $id)->where('status', 'active');

            if ($lock) {
                $workspace->sharedLock();
                $membership->sharedLock();
            }

            if ($workspace->first() === null || $membership->first() === null) {
                throw new AuthorizationException('CRM workspace membership is missing or revoked.');
            }
        }
    }

    public function workspace(ExecutionContext $ctx, bool $accountLevel): ?string
    {
        if ($accountLevel) {
            if (! $ctx->allowAccountLevel) {
                throw new AuthorizationException('CRM account-level writes require an explicit capability.');
            }

            return null;
        }
        $workspace = $ctx->tenant->workspace ?? throw new AuthorizationException('CRM workspace write requires a workspace.');

        return $workspace->id;
    }

    public function id(string|int $id): void
    {
        $valid = is_string($id) && Str::isUuid($id);

        if (! $valid) {
            throw new AuthorizationException('CRM reference has an invalid UUID.');
        }
    }
}
