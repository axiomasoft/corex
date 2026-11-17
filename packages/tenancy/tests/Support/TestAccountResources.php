<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support;

use Closure;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ProvisionParams;
use CoreX\Tenancy\Provisioning\Contracts\AccountResources;
use CoreX\Tenancy\Provisioning\Data\AccountArchive;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Test host only: each physical database must be an explicitly isolated *_test resource. */
final readonly class TestAccountResources implements AccountResources
{
    public function __construct(private ?Closure $writer = null, private ?Closure $verifier = null) {}

    public function exists(AccountRef $account): bool
    {
        $database = $this->database($account);

        return DB::connection('pgsql')->table('pg_database')->where('datname', $database)->exists();
    }

    public function provision(AccountRef $account, ProvisionParams $params): void
    {
        $database = $this->database($account);

        if (! str_ends_with($database, '_test')) {
            $database .= '_test';
            DB::connection('pgsql')->table('root_accounts')->where('id', $account->id)->update(['db_name' => $database]);
        }
        $template = DB::connection('pgsql')->table('root_templates')->where('version', $params->templateVersion)->value('db_name');
        $this->assertIsolated($database);
        $this->assertIsolated($template);
        DB::connection('pgsql')->statement('CREATE DATABASE "'.$database.'" TEMPLATE "'.$template.'"');
    }

    public function export(AccountRef $account, string $exportId, string $kind): AccountArchive
    {
        if ($this->writer === null) {
            throw new RuntimeException('No tenant export writer configured by the test host.');
        }

        if ($this->verifier === null) {
            throw new RuntimeException('A qualified archive verifier is required by the test host.');
        }
        $result = ($this->writer)($account, $exportId);

        return new AccountArchive($result['storage_path'], $result['size_bytes']);
    }

    public function verifyArchive(AccountRef $account, AccountArchive $archive): bool
    {
        if ($this->verifier === null) {
            throw new RuntimeException('A qualified archive verifier is required by the test host.');
        }

        return ($this->verifier)($archive->reference, $archive->sizeBytes);
    }

    public function drop(AccountRef $account, AccountArchive $archive): void
    {
        if (! $this->verifyArchive($account, $archive)) {
            throw new RuntimeException('Archive rejected by host.');
        }
        $this->deleteDatabase($account);
    }

    public function discardUnclaimed(AccountRef $account): void
    {
        $row = DB::connection('pgsql')->table('root_accounts')->where('id', $account->id)->first();

        if ($row === null || $row->status !== 'provisioning' || ! $row->pool_cleanup || $row->owner_identity_id !== null) {
            throw new RuntimeException('Refusing to discard a claimed test resource.');
        }
        $this->deleteDatabase($account);
    }

    private function deleteDatabase(AccountRef $account): void
    {
        $database = $this->database($account);
        $this->assertIsolated($database);
        DB::connection('pgsql')->statement('DROP DATABASE IF EXISTS "'.$database.'"');
    }

    private function database(AccountRef $account): string
    {
        $name = DB::connection('pgsql')->table('root_accounts')->where('id', $account->id)->value('db_name');

        if (! is_string($name)) {
            throw new RuntimeException('Unknown test account resource.');
        }

        return $name;
    }

    private function assertIsolated(mixed $name): void
    {
        if (! app()->environment('testing') || ! str_ends_with(DB::connection('pgsql')->getDatabaseName(), '_test')
            || ! is_string($name) || preg_match('/^[a-z][a-z0-9_]*_test$/D', $name) !== 1) {
            throw new RuntimeException('Physical fixtures require an isolated *_test database.');
        }
    }
}
