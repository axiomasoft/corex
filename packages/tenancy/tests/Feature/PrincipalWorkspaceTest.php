<?php

declare(strict_types=1);

use CoreX\Tenancy\Contracts\PrincipalWorkspaceResolver;
use CoreX\Tenancy\Models\User;
use CoreX\Tenancy\Models\Workspace;
use CoreX\Tenancy\Models\WorkspaceMember;
use CoreX\Tenancy\Workspaces\DatabaseWorkspaceResolver;
use CoreX\Tenancy\Workspaces\NoDefaultWorkspaceException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses()->group('pg');

it('resolves explicit principals across two isolated account databases without auth state or membership caches', function (): void {
    $control = DB::connection('pgsql');
    expect($control->getDatabaseName())->toEndWith('_test');
    $original = DB::getDefaultConnection();
    $suffix = bin2hex(random_bytes(6));
    $databases = ['principal_a' => 'corex_principal_a_'.$suffix.'_test', 'principal_b' => 'corex_principal_b_'.$suffix.'_test'];
    $principalId = (string) Str::uuid7();
    $workspaceIds = [];

    try {
        foreach ($databases as $connection => $database) {
            expect($database)->toMatch('/^corex_principal_[ab]_[a-f0-9]+_test$/D');
            $control->statement('CREATE DATABASE "'.$database.'"');
            config()->set('database.connections.'.$connection, array_replace(config('database.connections.pgsql'), ['database' => $database]));
            DB::setDefaultConnection($connection);
            expect(DB::connection()->getDatabaseName())->toBe($database);

            foreach ([__DIR__.'/../../vendor/corex/core/database/migrations/pg', __DIR__.'/../../database/migrations/tenant'] as $path) {
                $this->artisan('migrate', ['--database' => $connection, '--path' => realpath($path), '--realpath' => true, '--force' => true])->assertExitCode(0);
            }

            User::query()->forceCreate(['id' => $principalId, 'email' => 'principal@example.test', 'name' => 'Principal']);
            $id = (string) Str::uuid7();
            $workspace = Workspace::query()->forceCreate(['id' => $id, 'path' => str_replace('-', '', $id), 'type' => 'company', 'name' => 'Account workspace', 'slug' => 'same-slug']);
            WorkspaceMember::query()->create(['workspace_id' => $id, 'user_id' => $principalId, 'status' => 'active', 'is_default' => true]);
            $workspaceIds[$connection] = $workspace->id;
        }

        $resolver = app(PrincipalWorkspaceResolver::class);
        expect($resolver)->toBeInstanceOf(DatabaseWorkspaceResolver::class);
        // No auth binding is involved; the same resolver is reused A -> B -> A.
        foreach (['principal_a', 'principal_b', 'principal_a'] as $connection) {
            DB::setDefaultConnection($connection);
            expect($resolver->resolveFor($principalId, 'same-slug')->id)->toBe($workspaceIds[$connection])
                ->and($resolver->resolveFor($principalId, null)->id)->toBe($workspaceIds[$connection]);
        }

        DB::setDefaultConnection('principal_b');
        WorkspaceMember::query()->where('user_id', $principalId)->update(['status' => 'suspended']);
        expect(fn () => $resolver->resolveFor($principalId, 'same-slug'))->toThrow(AuthorizationException::class)
            ->and(fn () => $resolver->resolveFor($principalId, null))->toThrow(NoDefaultWorkspaceException::class)
            ->and(fn () => $resolver->resolveFor((string) Str::uuid7(), 'same-slug'))->toThrow(AuthorizationException::class)
            ->and(fn () => $resolver->resolveFor($principalId, 'foreign'))->toThrow(ModelNotFoundException::class)
            ->and(fn () => $resolver->resolveFor('', 'same-slug'))->toThrow(AuthorizationException::class)
            ->and(fn () => $resolver->resolve('same-slug'))->toThrow(AuthorizationException::class);
        DB::setDefaultConnection('principal_a');
        expect($resolver->resolveFor($principalId, 'same-slug')->id)->toBe($workspaceIds['principal_a']);
    } finally {
        DB::setDefaultConnection($original);
        foreach ($databases as $connection => $database) {
            DB::purge($connection);
            $control->statement('DROP DATABASE IF EXISTS "'.$database.'" WITH (FORCE)');
        }
    }
});
