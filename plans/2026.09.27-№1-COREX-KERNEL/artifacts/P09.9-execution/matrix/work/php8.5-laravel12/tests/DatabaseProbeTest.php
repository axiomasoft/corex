<?php
declare(strict_types=1);

use CoreX\Tenancy\Provisioning\Internal\DatabaseValue;
use Illuminate\Database\Capsule\Manager;

it('decodes real PDO rows on '.getenv('SPIKE_DRIVER'), function (): void {
    $driver = getenv('SPIKE_DRIVER');
    $database = getenv('PGSQL_DATABASE');
    if ($driver === 'pgsql' && preg_match('/^[a-z0-9_]+_test$/D', $database) !== 1) {
        throw new RuntimeException('Unsafe test database.');
    }
    $capsule = new Manager;
    $capsule->addConnection($driver === 'sqlite'
        ? ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']
        : ['driver' => 'pgsql', 'host' => getenv('PGSQL_HOST'), 'port' => getenv('PGSQL_PORT'),
           'database' => $database, 'username' => getenv('PGSQL_USERNAME'),
           'password' => getenv('PGSQL_PASSWORD'), 'charset' => 'utf8', 'prefix' => '']);
    $connection = $capsule->getConnection();
    $actualDriver = $connection->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
    expect($actualDriver)->toBe($driver);
    $connection->statement('CREATE TEMPORARY TABLE corex_spike_values (value BIGINT NOT NULL, label TEXT NOT NULL)');
    $connection->insert('INSERT INTO corex_spike_values (value, label) VALUES (?, ?)', [23, 'tenancy-spike']);
    $row = DatabaseValue::row($connection->selectOne('SELECT value, label FROM corex_spike_values'));
    expect(DatabaseValue::integer($row->value))->toBe(23)
        ->and(DatabaseValue::text($row->label))->toBe('tenancy-spike')
        ->and(DatabaseValue::decimal($row->value))->toBe('23');
    $actualDatabase = $driver === 'pgsql'
        ? $connection->selectOne('SELECT current_database() AS name')->name : ':memory:';
    file_put_contents(getenv('SPIKE_DB_WITNESS'), json_encode([
        'driver' => $actualDriver, 'database' => $actualDatabase,
        'server_version' => $connection->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION),
        'laravel' => \Illuminate\Foundation\Application::VERSION,
        'fixture' => 'connection-local temporary table; no package schema migration',
    ], JSON_THROW_ON_ERROR));
    $connection->disconnect();
});
