<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\PhysicalBinding;
use CoreX\Context\Data\WorkspaceRef;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Application;
use Illuminate\Support\Sleep;
use RuntimeException;
use Throwable;

final class Scenario
{
    public const string ACCOUNT_A = '10000000-0000-7000-8000-000000000001';

    public const string ACCOUNT_B = '10000000-0000-7000-8000-000000000002';

    public const string BOXED = '00000000-0000-7000-8000-000000000000';

    public const string W1 = '20000000-0000-7000-8000-000000000001';

    public const string W2 = '20000000-0000-7000-8000-000000000002';

    public const string USER_A = '30000000-0000-7000-8000-000000000001';

    public const string USER_B = '30000000-0000-7000-8000-000000000002';

    public const string RECORD = '40000000-0000-7000-8000-000000000001';

    public const string DEPLOYMENT = 'corex-installed-scenario';

    private int $assertions = 0;

    public static function boot(): Application
    {
        require_once getcwd().'/vendor/autoload.php';
        $app = require getcwd().'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        set_exception_handler(static function (Throwable $error): never {
            fwrite(STDERR, (string) $error.PHP_EOL);
            exit(1);
        });

        return $app;
    }

    public static function account(string $id): AccountRef
    {
        return new AccountRef($id, $id === self::ACCOUNT_B ? 'account-b' : 'account-a', 'active', [], []);
    }

    public static function workspace(string $id): WorkspaceRef
    {
        $slug = match ($id) {
            self::W1 => 'workspace-1',
            self::W2 => 'workspace-2',
            default => throw new RuntimeException('Unknown fixture workspace.'),
        };

        return new WorkspaceRef($id, $slug, 'company', str_replace('-', '_', $slug), null);
    }

    public static function binding(string $id, int $generation, string $epoch, string $account): PhysicalBinding
    {
        return new PhysicalBinding(self::DEPLOYMENT, $id, $generation, 'incarnation-'.$account, str_repeat($epoch, 64));
    }

    public static function connection(string $role): Connection
    {
        return resolve('db')->connection('scenario_'.$role);
    }

    public static function describe(ConnectionInterface $connection): array
    {
        $row = $connection->selectOne('SELECT current_database() AS database, current_schema() AS namespace, pg_backend_pid() AS session, version() AS backend');

        if ($row === null || ! str_starts_with($row->backend, 'PostgreSQL ')) {
            throw new RuntimeException('Scenario requires a live PostgreSQL session.');
        }

        return ['database' => $row->database, 'namespace' => $row->namespace, 'session' => (int) $row->session, 'backend' => $row->backend];
    }

    public function check(bool $condition, string $description): void
    {
        if (! $condition) {
            throw new RuntimeException($description);
        }
        $this->assertions++;
    }

    public function same(mixed $expected, mixed $actual, string $description): void
    {
        $this->check($expected === $actual, $description.' Expected '.json_encode($expected, JSON_THROW_ON_ERROR).', observed '.json_encode($actual, JSON_THROW_ON_ERROR).'.');
    }

    public static function barrier(string $name, array $state = []): void
    {
        $directory = getenv('COREX_SCENARIO_BARRIERS');

        if (! is_string($directory) || ! is_dir($directory)) {
            throw new RuntimeException('Scenario barriers are unavailable.');
        }
        $marker = $directory.'/'.$name;
        $temporary = $marker.'.'.getmypid().'.tmp';
        file_put_contents($temporary, json_encode(['pid' => getmypid(), ...$state], JSON_THROW_ON_ERROR), LOCK_EX);
        rename($temporary, $marker.'.reached');
        while (! is_file($marker.'.release')) {
            Sleep::usleep(25_000);
        }
    }

    public static function barrierRead(string $name): array
    {
        $directory = getenv('COREX_SCENARIO_BARRIERS');
        $path = $directory.'/'.$name.'.reached';

        if (! is_file($path)) {
            throw new RuntimeException('Expected peer barrier '.$name.'.');
        }

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function report(ConnectionInterface $connection, array $refs, array $before, array $after): void
    {
        $backend = self::describe($connection);
        echo json_encode([
            'pid' => getmypid(),
            'assertions' => $this->assertions,
            'backend' => $backend['backend'],
            'connection' => $backend,
            'refs' => $refs,
            'before' => $before,
            'after' => $after,
        ], JSON_THROW_ON_ERROR).PHP_EOL;
        flush();
    }
}
