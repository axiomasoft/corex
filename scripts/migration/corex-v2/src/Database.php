<?php

declare(strict_types=1);

namespace CoreX\OfflineMigration;

use Generator;
use SQLite3;

function database(string $path, bool $write): SQLite3
{
    // SQLITE3_OPEN_CREATE is deliberately absent.
    $db = new SQLite3($path, $write ? SQLITE3_OPEN_READWRITE : SQLITE3_OPEN_READONLY);
    $db->enableExceptions(true);
    $db->busyTimeout(5000);
    $db->exec('PRAGMA trusted_schema = OFF');
    $db->exec('PRAGMA foreign_keys = ON');

    if (! $write) {
        $db->exec('PRAGMA query_only = ON');
    }

    return $db;
}

/**
 * @param  list<int|float|string|null>  $parameters
 * @return Generator<int, array<string, int|float|string|null>, void, void>
 */
function rows(SQLite3 $db, string $sql, array $parameters = []): Generator
{
    $statement = $db->prepare($sql);
    check($statement !== false, 'Cannot prepare database read.');

    try {
        foreach ($parameters as $index => $value) {
            check($statement->bindValue($index + 1, $value), 'Cannot bind database read parameter.');
        }
        $result = $statement->execute();
        check($result !== false, 'Cannot execute database read.');

        try {
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $fields = [];
                foreach ($row as $key => $value) {
                    check((is_scalar($value) || $value === null) && ! is_bool($value), 'Unexpected SQLite row field.');
                    $fields[$key] = $value;
                }
                yield $fields;
            }
        } finally {
            $result->finalize();
        }
    } finally {
        $statement->close();
    }
}

/** @param list<int|float|string|null> $parameters */
function execute(SQLite3 $db, string $sql, array $parameters): void
{
    $statement = $db->prepare($sql);
    check($statement !== false, 'Cannot prepare database write.');

    try {
        foreach ($parameters as $index => $value) {
            check($statement->bindValue($index + 1, $value), 'Cannot bind database write parameter.');
        }
        $result = $statement->execute();
        check($result !== false, 'Cannot execute database write.');
        $result->finalize();
    } finally {
        $statement->close();
    }
}

function schemaFingerprint(SQLite3 $db): string
{
    return digestRows(rows($db, 'SELECT type, name, tbl_name, sql FROM main.sqlite_schema ORDER BY name COLLATE BINARY'));
}

/** @return array{device: string, inode: string} */
function fileIdentity(string $path): array
{
    clearstatcache(true, $path);
    $stat = @stat($path);
    check($stat !== false && is_file($path), "Database file disappeared [{$path}].");

    return ['device' => (string) $stat['dev'], 'inode' => (string) $stat['ino']];
}

function fileDigest(string $path): string
{
    $hash = @hash_file('sha256', $path);
    check($hash !== false, "Cannot hash snapshot [{$path}].");

    return $hash;
}
