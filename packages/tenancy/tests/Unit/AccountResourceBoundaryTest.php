<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ProvisionParams;
use CoreX\Tenancy\Provisioning\Contracts\AccountResources;
use CoreX\Tenancy\Provisioning\Data\AccountArchive;
use CoreX\Tenancy\Provisioning\Internal\DatabaseTenantLifecycle;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Events\Dispatcher;
use Psr\Clock\ClockInterface;

/** Isolated metadata store: physical resources are owned by the host fixture. */
function accountResourceRig(): array
{
    $connection = new SQLiteConnection(new PDO('sqlite::memory:'), ':memory:');
    $connection->getSchemaBuilder()->create('root_account_exports', static function (Blueprint $table): void {
        $table->string('id')->primary();
        $table->string('account_id');
        $table->string('kind');
        $table->string('status');
        $table->text('formats');
        $table->string('storage_path')->nullable();
        $table->bigInteger('size_bytes')->nullable();
        $table->dateTime('purge_after')->nullable();
        $table->dateTime('url_expires_at')->nullable();
        $table->timestamps();
    });
    $connections = new ConnectionResolver(['root' => $connection]);
    $clock = new class implements ClockInterface
    {
        public function now(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-11-17T00:00:00Z');
        }
    };
    $resources = new class implements AccountResources
    {
        public bool $verified = true;

        public array $calls = [];

        public function exists(AccountRef $account): bool
        {
            return false;
        }

        public function provision(AccountRef $account, ProvisionParams $params): void
        {
            $this->calls[] = 'provision:'.$account->id;
        }

        public function export(AccountRef $account, string $exportId, string $kind): AccountArchive
        {
            $this->calls[] = 'export:'.$account->id;

            return new AccountArchive('owner-export:'.$exportId, 100);
        }

        public function verifyArchive(AccountRef $account, AccountArchive $archive): bool
        {
            $this->calls[] = 'verify:'.$account->id;

            return $this->verified;
        }

        public function drop(AccountRef $account, AccountArchive $archive): void
        {
            $this->calls[] = 'drop:'.$account->id;
        }

        public function discardUnclaimed(AccountRef $account): void
        {
            $this->calls[] = 'discard:'.$account->id;
        }
    };
    $events = new Dispatcher;

    return [new DatabaseTenantLifecycle('root', $connections, $clock, $events, $resources), $resources, $connection];
}

it('keeps account export metadata while delegating every physical effect to the owner', function (): void {
    [$lifecycle, $resources, $connection] = accountResourceRig();
    $account = new AccountRef('account-a', 'a', 'active', [], []);
    $id = $lifecycle->export($account, 'manual');
    expect($connection->table('root_account_exports')->where('id', $id)->value('status'))->toBe('ready');
    $lifecycle->drop($account);
    expect($resources->calls)->toBe(['export:account-a', 'verify:account-a', 'verify:account-a', 'drop:account-a']);
});

it('does not mark an unverified owner archive ready and never requests deletion', function (): void {
    [$lifecycle, $resources, $connection] = accountResourceRig();
    $resources->verified = false;
    $account = new AccountRef('account-a', 'a', 'active', [], []);
    expect(fn () => $lifecycle->export($account, 'manual'))->toThrow(RuntimeException::class, 'physical owner did not verify');
    expect($connection->table('root_account_exports')->value('status'))->toBe('running');
    expect(fn () => $lifecycle->drop($account))->toThrow(RuntimeException::class, 'no ready export');
    expect($resources->calls)->toBe(['export:account-a', 'verify:account-a']);
});

it('requires a fresh owner verification before deletion and never borrows another account archive', function (): void {
    [$lifecycle, $resources] = accountResourceRig();
    $account = new AccountRef('account-a', 'a', 'active', [], []);
    $lifecycle->export($account, 'manual');
    $resources->verified = false;
    expect(fn () => $lifecycle->drop($account))->toThrow(RuntimeException::class, 'physical owner did not verify');
    expect(fn () => $lifecycle->drop(new AccountRef('account-b', 'b', 'active', [], [])))->toThrow(RuntimeException::class, 'no ready export');
    expect($resources->calls)->toBe(['export:account-a', 'verify:account-a', 'verify:account-a']);
});
