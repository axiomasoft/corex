<?php

declare(strict_types=1);

namespace App\Scenario;

use Closure;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Data\ScopePrincipal;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Database\DatabaseManager;

final class ScenarioPrincipalAuthority implements WorkPrincipalAuthority
{
    private ?string $service = null;

    public function __construct(private readonly Factory $auth, private readonly DatabaseManager $database) {}

    public function authenticate(TenantContext $context, string $id): void
    {
        $this->auth->guard()->setUser($this->user($context, $id));
    }

    public function current(TenantContext $context): ScopePrincipal
    {
        if ($this->service !== null) {
            $this->assertService($context, $this->service);

            return new ScopePrincipal('service', $this->service);
        }
        $user = $this->auth->guard()->user();

        if (! $user instanceof ScenarioUser) {
            throw new ScopeContextMismatch('The host has no authenticated principal.');
        }
        $this->user($context, $user->principalId());

        return new ScopePrincipal('user', $user->principalId());
    }

    public function run(DataScope $scope, TenantContext $context, bool $durable, Closure $work): mixed
    {
        if ($scope->principalKind === 'service') {
            if (! $durable && $this->current($context) != new ScopePrincipal('service', $scope->principalId)) {
                throw new ScopeContextMismatch('The named service is outside the active host grant.');
            }

            return $this->forService($context, $scope->principalId, $work);
        }
        $guard = $this->auth->guard();
        $before = $guard->user();

        if ($scope->principalKind !== 'user' || (! $durable && $this->current($context)->id !== $scope->principalId)) {
            throw new ScopeContextMismatch('The requested user is outside the host work authority.');
        }
        $guard->setUser($this->user($context, $scope->principalId));
        $previousService = $this->service;
        $this->service = null;

        try {
            return $work();
        } finally {
            $this->service = $previousService;
            // Laravel's session guard is the fixture's real request principal.
            $guard->forgetUser();

            if ($before !== null) {
                $guard->setUser($before);
            }
        }
    }

    public function forService(TenantContext $context, string $service, Closure $work): mixed
    {
        $this->assertService($context, $service);
        $previous = $this->service;
        $this->service = $service;

        try {
            return $work();
        } finally {
            $this->service = $previous;
        }
    }

    private function assertService(TenantContext $context, string $service): void
    {
        if ($context->workspace !== null || $service !== 'corex.scheduler-sweep'
            || ! $this->database->connection()->table('scenario_service_grants')->where('service', $service)
                ->where('account', $context->id())->exists()) {
            throw new ScopeContextMismatch('The fixed root service has no grant on this account.');
        }
    }

    private function user(TenantContext $context, string $id): ScenarioUser
    {
        $connection = $this->database->connection();
        $user = $connection->table('users')->where('id', $id)->where('is_active', true)->whereNull('deleted_at')->first();
        $granted = $context->workspace === null
            ? $connection->table('scenario_account_grants')->where('principal_id', $id)->exists()
            : $connection->table('wsp_workspace_members')->where('user_id', $id)
                ->where('workspace_id', $context->workspace->id)->where('status', 'active')->exists();

        if ($user === null || ! $granted) {
            throw new ScopeContextMismatch('The named user lacks a live membership for the requested context.');
        }

        return new ScenarioUser((array) $user);
    }
}
