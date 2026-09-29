<?php

declare(strict_types=1);

use CoreX\Tenancy\Identity\Models\User;
use CoreX\Tenancy\Workspace\DatabaseWorkspaceResolver;
use CoreX\Tenancy\Workspace\InvitationNotAcceptableException;
use CoreX\Tenancy\Workspace\Models\Invitation;
use CoreX\Tenancy\Workspace\Models\Workspace;
use CoreX\Tenancy\Workspace\Models\WorkspaceMember;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

const CORE_MIGRATIONS_PATH_WSP = __DIR__.'/../../vendor/axiomasoft/corex/database/migrations/pg';
const TENANT_MIGRATIONS_PATH_WSP = __DIR__.'/../../database/migrations/tenant';

beforeEach(function (): void {
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');

    // Assembles the golden-template chain (D14/C4): axiomasoft/corex owns
    // sys_departments/sys_department_user, axiomasoft/corex-tenancy owns
    // users/wsp_*/the integration FK — two ordered migrate calls, same as
    // a real golden-template build.
    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(CORE_MIGRATIONS_PATH_WSP),
        '--realpath' => true,
        '--force' => true,
    ])->run();

    $this->artisan('migrate', [
        '--database' => 'pgsql',
        '--path' => realpath(TENANT_MIGRATIONS_PATH_WSP),
        '--realpath' => true,
        '--force' => true,
    ])->run();
});

function makeUserWsp(array $overrides = []): User
{
    return User::query()->create(array_merge([
        'id' => (string) Str::uuid7(),
        'email' => Str::random(8).'@example.test',
        'name' => 'Test User',
    ], $overrides));
}

function makeWorkspaceWsp(array $overrides = []): Workspace
{
    $id = (string) Str::uuid7();

    // forceCreate — 'id' is deliberately not in Workspace::$fillable (ids
    // are generated, not caller-supplied); the test needs a known id upfront
    // to assert against.
    return Workspace::query()->forceCreate(array_merge([
        'id' => $id,
        'path' => str_replace('-', '', $id),
        'type' => 'company',
        'name' => 'Acme',
        'slug' => 'acme-'.Str::random(6),
    ], $overrides));
}

/** Swaps the `auth` container binding for a {@see Guard} stub — the `auth()` helper's return type forces an actual Guard/Factory instance, even though {@see DatabaseWorkspaceResolver} only calls id(). */
function actingAsIdWsp(?string $userId): void
{
    app()->instance('auth', new class($userId) implements Guard
    {
        public function __construct(private readonly ?string $id) {}

        public function check(): bool
        {
            return $this->id !== null;
        }

        public function guest(): bool
        {
            return $this->id === null;
        }

        public function user(): ?Authenticatable
        {
            return null;
        }

        public function id(): ?string
        {
            return $this->id;
        }

        public function validate(array $credentials = []): bool
        {
            return false;
        }

        public function hasUser(): bool
        {
            return $this->id !== null;
        }

        public function setUser(Authenticatable $user): static
        {
            return $this;
        }
    });
}

it('AC-17: enforces exactly one default workspace via partial-unique index', function (): void {
    makeWorkspaceWsp(['is_default' => true]);

    expect(fn () => makeWorkspaceWsp(['is_default' => true]))->toThrow(QueryException::class);
});

it('AC-17: enforces unique slug among living workspaces', function (): void {
    makeWorkspaceWsp(['slug' => 'dup']);

    expect(fn () => makeWorkspaceWsp(['slug' => 'dup']))->toThrow(QueryException::class);
});

it('AC-17: a soft-deleted workspace frees its slug and default flag for reuse', function (): void {
    $first = makeWorkspaceWsp(['slug' => 'reuse', 'is_default' => true]);
    $first->delete();

    $second = makeWorkspaceWsp(['slug' => 'reuse', 'is_default' => true]);

    expect($second->slug)->toBe('reuse');
});

it('AC-17: parent_id + path stay consistent under a subtree move (path <@ predicate)', function (): void {
    $parentA = makeWorkspaceWsp(['name' => 'Parent A']);
    $parentB = makeWorkspaceWsp(['name' => 'Parent B']);
    $childId = (string) Str::uuid7();
    $child = makeWorkspaceWsp([
        'id' => $childId,
        'name' => 'Child',
        'parent_id' => $parentA->id,
        'path' => $parentA->path.'.'.str_replace('-', '', $childId),
    ]);

    // A real move re-parents + recomputes the ltree label for the moved
    // subtree (out of this item's Scope Included — application-level Move
    // is deferred); this proves the predicate a Move implementation would
    // rely on: path <@ correctly reflects the NEW parent once both columns
    // are updated together.
    $child->forceFill(['parent_id' => $parentB->id, 'path' => $parentB->path.'.'.str_replace('-', '', $childId)])->save();

    $underB = Workspace::query()->whereRaw('path <@ ?::ltree', [$parentB->path])->pluck('id');
    $underA = Workspace::query()->whereRaw('path <@ ?::ltree', [$parentA->path])->pluck('id');

    expect($underB)->toContain($childId)
        ->and($underA)->not->toContain($childId)
        ->and($child->fresh()->parent_id)->toBe($parentB->id);
});

it('AC-17: parent RESTRICT blocks deleting a workspace that still has children', function (): void {
    $parent = makeWorkspaceWsp();
    makeWorkspaceWsp(['parent_id' => $parent->id]);

    expect(fn () => DB::table('wsp_workspaces')->where('id', $parent->id)->delete())->toThrow(QueryException::class);
});

it('AC-18: resolve(slug) 403s without an active membership row', function (): void {
    $workspace = makeWorkspaceWsp();
    $user = makeUserWsp();
    actingAsIdWsp($user->id);

    expect(fn () => (new DatabaseWorkspaceResolver)->resolve($workspace->slug))->toThrow(AuthorizationException::class);
});

it('AC-18: resolve(slug) 403s a suspended member', function (): void {
    $workspace = makeWorkspaceWsp();
    $user = makeUserWsp();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => 'suspended']);
    actingAsIdWsp($user->id);

    expect(fn () => (new DatabaseWorkspaceResolver)->resolve($workspace->slug))->toThrow(AuthorizationException::class);
});

it('AC-18: resolve(slug) succeeds for an active member', function (): void {
    $workspace = makeWorkspaceWsp();
    $user = makeUserWsp();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => 'active']);
    actingAsIdWsp($user->id);

    $ref = (new DatabaseWorkspaceResolver)->resolve($workspace->slug);

    expect($ref->id)->toBe($workspace->id)
        ->and($ref->slug)->toBe($workspace->slug);
});

it('AC-18: resolve(slug) throws ModelNotFoundException for a nonexistent slug', function (): void {
    actingAsIdWsp((string) Str::uuid7());

    expect(fn () => (new DatabaseWorkspaceResolver)->resolve('does-not-exist'))->toThrow(ModelNotFoundException::class);
});

it('AC-18: resolve(null) returns the user default workspace', function (): void {
    $workspace = makeWorkspaceWsp();
    $user = makeUserWsp();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'status' => 'active', 'is_default' => true]);
    actingAsIdWsp($user->id);

    $ref = (new DatabaseWorkspaceResolver)->resolve(null);

    expect($ref->id)->toBe($workspace->id);
});

it('AC-18: resolve(null) throws when the user has no default workspace membership', function (): void {
    $user = makeUserWsp();
    actingAsIdWsp($user->id);

    expect(fn () => (new DatabaseWorkspaceResolver)->resolve(null))->toThrow(RuntimeException::class);
});

it('AC-18: a partner-workspace membership does not grant visibility of its parent', function (): void {
    $parent = makeWorkspaceWsp(['type' => 'company']);
    $partner = makeWorkspaceWsp(['type' => 'partner', 'parent_id' => $parent->id]);
    $user = makeUserWsp();
    WorkspaceMember::query()->create(['workspace_id' => $partner->id, 'user_id' => $user->id, 'status' => 'active']);
    actingAsIdWsp($user->id);

    $resolver = new DatabaseWorkspaceResolver;

    expect($resolver->resolve($partner->slug)->id)->toBe($partner->id)
        ->and(fn () => $resolver->resolve($parent->slug))->toThrow(AuthorizationException::class);
});

it('AC-19: accept() creates membership, marks accepted, and checks users_max', function (): void {
    $workspace = makeWorkspaceWsp();
    $inviter = makeUserWsp();
    $invitee = makeUserWsp();

    $invitation = Invitation::query()->create([
        'email' => $invitee->email,
        'workspace_id' => $workspace->id,
        'invited_by' => $inviter->id,
        'token_hash' => hash('sha256', 'token-1'),
        'expires_at' => now()->addDays(7),
    ]);

    $member = $invitation->accept($invitee->id, usersMax: 10);

    expect($member)->not->toBeNull()
        ->and($member->workspace_id)->toBe($workspace->id)
        ->and($member->user_id)->toBe($invitee->id)
        ->and($invitation->fresh()->status)->toBe('accepted')
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('reloads a revoked invitation and rolls back membership when acceptance persistence fails', function (): void {
    $workspace = makeWorkspaceWsp();
    $inviter = makeUserWsp();
    $invitee = makeUserWsp();
    $invitation = Invitation::query()->create(['email' => $invitee->email, 'workspace_id' => $workspace->id, 'invited_by' => $inviter->id, 'token_hash' => hash('sha256', 'remediation'), 'expires_at' => now()->addDay()]);
    DB::table('wsp_invitations')->where('id', $invitation->id)->update(['status' => 'revoked']);
    expect(fn () => $invitation->accept($invitee->id, 1))->toThrow(InvitationNotAcceptableException::class);
    DB::table('wsp_invitations')->where('id', $invitation->id)->update(['status' => 'pending']);
    Invitation::updating(static fn () => throw new RuntimeException('acceptance write failed'));
    expect(fn () => $invitation->accept($invitee->id, 1))->toThrow(RuntimeException::class, 'acceptance write failed');
    expect(WorkspaceMember::query()->where('workspace_id', $workspace->id)->count())->toBe(0)
        ->and($invitation->fresh()->status)->toBe('pending');
});

it('counts only active memberships against invitation quota', function (): void {
    $workspace = makeWorkspaceWsp();
    $inviter = makeUserWsp();
    $invitee = makeUserWsp();
    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $inviter->id, 'status' => 'suspended']);
    $invitation = Invitation::query()->create(['email' => $invitee->email, 'workspace_id' => $workspace->id, 'invited_by' => $inviter->id, 'token_hash' => hash('sha256', 'active-quota'), 'expires_at' => now()->addDay()]);
    expect($invitation->accept($invitee->id, 1)->status)->toBe('active');
});

it('serializes acceptance of separate invitations competing for one workspace slot', function (): void {
    $workspace = makeWorkspaceWsp();
    $inviter = makeUserWsp();
    $firstUser = makeUserWsp();
    $secondUser = makeUserWsp();
    $make = fn (User $user): Invitation => Invitation::query()->create([
        'email' => $user->email, 'workspace_id' => $workspace->id, 'invited_by' => $inviter->id,
        'token_hash' => hash('sha256', $user->id), 'expires_at' => now()->addDay(),
    ]);
    $first = $make($firstUser);
    $second = $make($secondUser);
    config(['database.connections.quota_race' => config('database.connections.pgsql')]);
    $other = DB::connection('quota_race');
    $other->beginTransaction();
    $other->statement("SET LOCAL lock_timeout = '50ms'");
    $observed = false;
    DB::listen(function ($query) use (&$observed, $second, $secondUser): void {
        if ($observed || $query->connectionName !== 'pgsql' || ! str_contains($query->sql, '"wsp_workspaces"') || ! str_contains($query->sql, 'for update')) {
            return;
        }
        $observed = true;

        try {
            Invitation::on('quota_race')->findOrFail($second->id)->accept($secondUser->id, 1);
            test()->fail('Concurrent acceptance passed a locked quota resource.');
        } catch (QueryException $exception) {
            expect($exception->errorInfo[0])->toBe('55P03');
        }
    });

    try {
        $first->accept($firstUser->id, 1);
        expect($observed)->toBeTrue();
        expect(fn () => Invitation::on('quota_race')->findOrFail($second->id)->accept($secondUser->id, 1))
            ->toThrow(InvitationNotAcceptableException::class);
        expect(WorkspaceMember::query()->where('workspace_id', $workspace->id)->count())->toBe(1)
            ->and($second->fresh()->status)->toBe('pending');
    } finally {
        $other->rollBack();
        DB::purge('quota_race');
    }
});

it('AC-19: accept() refuses once the users_max limit is already reached', function (): void {
    $workspace = makeWorkspaceWsp();
    $inviter = makeUserWsp();

    WorkspaceMember::query()->create(['workspace_id' => $workspace->id, 'user_id' => $inviter->id, 'status' => 'active']);

    $invitee = makeUserWsp();
    $invitation = Invitation::query()->create([
        'email' => $invitee->email,
        'workspace_id' => $workspace->id,
        'invited_by' => $inviter->id,
        'token_hash' => hash('sha256', 'token-2'),
        'expires_at' => now()->addDays(7),
    ]);

    expect(fn () => $invitation->accept($invitee->id, usersMax: 1))->toThrow(InvitationNotAcceptableException::class);
});

it('AC-19: accept() refuses a revoked invitation (410-class error)', function (): void {
    $workspace = makeWorkspaceWsp();
    $inviter = makeUserWsp();
    $invitee = makeUserWsp();

    $invitation = Invitation::query()->create([
        'email' => $invitee->email,
        'workspace_id' => $workspace->id,
        'invited_by' => $inviter->id,
        'token_hash' => hash('sha256', 'token-3'),
        'status' => 'revoked',
        'expires_at' => now()->addDays(7),
    ]);

    expect(fn () => $invitation->accept($invitee->id, usersMax: 10))->toThrow(InvitationNotAcceptableException::class);
});

it('AC-19: accept() refuses an expired invitation even if its status is still pending (lazy expiry)', function (): void {
    $workspace = makeWorkspaceWsp();
    $inviter = makeUserWsp();
    $invitee = makeUserWsp();

    $invitation = Invitation::query()->create([
        'email' => $invitee->email,
        'workspace_id' => $workspace->id,
        'invited_by' => $inviter->id,
        'token_hash' => hash('sha256', 'token-4'),
        'expires_at' => now()->subMinute(),
    ]);

    expect(fn () => $invitation->accept($invitee->id, usersMax: 10))->toThrow(InvitationNotAcceptableException::class);
});

it('AC-19: wsp_invitations.role_id carries no FK — accepting with a nonexistent role does not fail', function (): void {
    $workspace = makeWorkspaceWsp();
    $inviter = makeUserWsp();
    $invitee = makeUserWsp();

    $invitation = Invitation::query()->create([
        'email' => $invitee->email,
        'workspace_id' => $workspace->id,
        'role_id' => (string) Str::uuid7(),
        'invited_by' => $inviter->id,
        'token_hash' => hash('sha256', 'token-5'),
        'expires_at' => now()->addDays(7),
    ]);

    $member = $invitation->accept($invitee->id, usersMax: 10);

    expect($member)->not->toBeNull();
});

it('Integration FK: all four cross-package FKs exist and hold', function (): void {
    $workspace = makeWorkspaceWsp();
    $head = makeUserWsp();

    $deptId = (string) Str::uuid7();
    DB::table('sys_departments')->insert([
        'id' => $deptId,
        'workspace_id' => $workspace->id,
        'name' => 'Sales',
        'head_user_id' => $head->id,
        'path' => str_replace('-', '', $deptId),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $member = makeUserWsp(['department_id' => $deptId]);

    DB::table('sys_department_user')->insert([
        'department_id' => $deptId,
        'user_id' => $member->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('sys_departments')->where('id', $deptId)->value('workspace_id'))->toBe($workspace->id)
        ->and(DB::table('users')->where('id', $member->id)->value('department_id'))->toBe($deptId);

    // sys_departments.workspace_id → wsp_workspaces is RESTRICT (NOT NULL column).
    expect(fn () => DB::table('wsp_workspaces')->where('id', $workspace->id)->delete())->toThrow(QueryException::class);
});
