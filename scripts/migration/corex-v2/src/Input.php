<?php

declare(strict_types=1);

namespace CoreX\OfflineMigration;

use JsonException;
use RuntimeException;

final class MigrationError extends RuntimeException {}

/** @phpstan-assert true $valid */
function check(bool $valid, string $message, int $exitCode = 2): void
{
    if (! $valid) {
        throw new MigrationError($message, $exitCode);
    }
}

/** The prototype's signed format uses ASCII object keys, safe integers, and no floats. */
function canonical(mixed $value): string
{
    if (is_array($value)) {
        if (array_is_list($value)) {
            return '['.implode(',', array_map(canonical(...), $value)).']';
        }
        ksort($value, SORT_STRING);
        $fields = [];
        foreach ($value as $key => $field) {
            check(is_string($key) && preg_match('/^[a-zA-Z_][a-zA-Z_0-9]*$/D', $key) === 1, 'Unsupported canonical JSON key.');
            $fields[] = canonical($key).':'.canonical($field);
        }

        return '{'.implode(',', $fields).'}';
    }

    check(! is_float($value) && (! is_int($value) || abs($value) <= 9007199254740991), 'Unsupported canonical JSON number.');

    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
}

function digest(mixed $value): string
{
    return hash('sha256', canonical($value));
}

/** @param iterable<mixed> $rows */
function digestRows(iterable $rows): string
{
    $context = hash_init('sha256');
    foreach ($rows as $row) {
        $bytes = canonical($row);
        hash_update($context, pack('N', strlen($bytes)).$bytes);
    }

    return hash_final($context);
}

function fileContents(string $path): string
{
    $data = @file_get_contents($path);
    check($data !== false, "Cannot read [{$path}].");

    return $data;
}

/** @return array<string, mixed> */
function readDocument(string $path): array
{
    $raw = fileContents($path);

    try {
        $document = json_decode($raw, associative: true, flags: JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new MigrationError("Invalid JSON document [{$path}].", 2);
    }
    $document = objectFields($document, "Expected an object [{$path}].");
    check(canonical($document) === trim($raw), "Expected canonical JSON [{$path}].");

    return $document;
}

/** @return array<string, mixed> */
function objectFields(mixed $value, string $error): array
{
    check(is_array($value) && ! array_is_list($value), $error);
    $object = [];
    foreach ($value as $key => $field) {
        check(is_string($key), $error);
        $object[$key] = $field;
    }

    return $object;
}

/**
 * @param  array<string, mixed>  $document
 * @param  list<string>  $expected
 */
function exactFields(array $document, array $expected, string $label): void
{
    $actual = array_keys($document);
    sort($actual);
    sort($expected);
    check($actual === $expected, "Unexpected fields in {$label}.");
}

/** @param array<string, mixed> $document */
function field(array $document, string $key): string
{
    $value = $document[$key] ?? null;
    check(is_string($value) && $value !== '', "Missing or invalid {$key}.");

    return $value;
}

/** @param array<string, mixed> $document */
function shaField(array $document, string $key, int $length = 64): string
{
    $value = field($document, $key);
    check(strlen($value) === $length && ctype_xdigit($value) && strtolower($value) === $value, "Invalid {$key}.");

    return $value;
}

function fullPath(string $path): string
{
    $resolved = realpath($path);
    check($resolved !== false && is_file($resolved), "Missing input [{$path}].");

    return $resolved;
}

/** @param array<string, mixed> $document */
function publish(string $path, array $document): void
{
    $temp = tempnam(dirname($path), '.corex-plan-');
    check($temp !== false, 'Cannot allocate output file.');

    try {
        check(realpath(dirname($temp)) === realpath(dirname($path)), 'Cannot write in output directory.');
        $bytes = canonical($document).PHP_EOL;
        check(file_put_contents($temp, $bytes) !== false && fileContents($temp) === $bytes, 'Cannot write complete output.');
        // Linking within one directory publishes atomically and can never overwrite another run.
        check(@link($temp, $path), "Cannot publish output [{$path}].");
    } finally {
        unlink($temp);
    }
}

/**
 * @param  array<string, mixed>  $sealed
 * @param  array<string, mixed>  $trust
 * @return array<string, mixed>
 */
function authenticated(array $sealed, string $purpose, array $trust): array
{
    exactFields($sealed, ['body', 'signature'], $purpose.' envelope');
    $body = objectFields($sealed['body'] ?? null, "Invalid {$purpose} body.");
    $signer = field($body, 'key_id');
    $reviewer = field($body, 'principal');
    $entry = objectFields($trust[$signer] ?? null, "Untrusted {$purpose} signer.");
    $purposes = $entry['purposes'] ?? null;
    check(($entry['principal'] ?? null) === $reviewer && is_array($purposes)
        && in_array($purpose, $purposes, true), "Untrusted {$purpose} signer.");
    $publicKey = base64_decode(field($entry, 'public_key'), true);
    $signature = base64_decode(field($sealed, 'signature'), true);
    check($publicKey !== false && strlen($publicKey) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
        && $signature !== false && strlen($signature) === SODIUM_CRYPTO_SIGN_BYTES
        && sodium_crypto_sign_verify_detached($signature, "corex-migrate/stage0/{$purpose}\n".canonical($body), $publicKey),
        "Invalid {$purpose} signature.");

    return $body;
}
