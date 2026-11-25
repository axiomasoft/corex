<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use App\Scenario\Scenario;
use RuntimeException;

final class InputIdentity
{
    public const string ENTITY = 'eq.equipment';

    public const string SERIAL = '01900000-0000-7000-8000-000000000001';

    public const string FIRST = Scenario::RECORD;

    public const string SECOND = '40000000-0000-7000-8000-000000000002';

    public static function fingerprint(array $input): string
    {
        return self::digest(self::stamp($input));
    }

    public static function digest(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    public static function stamp(array $input): array
    {
        $revision = resolve('db')->connection()->table('bulk_fixture_revisions')->sole();

        return [
            'kind' => $input['kind'],
            'source' => $input['source'],
            'schema' => $revision->schema_hash,
            'codec' => $revision->codec,
            'mapping' => $revision->mapping,
            'snapshot' => $revision->snapshot_revision,
            'content' => $input['kind'] === 'import' ? hash('sha256', self::content($input['source'])) : null,
        ];
    }

    public static function content(string $name): string
    {
        $file = match ($name) {
            'equipment' => 'equipment.ndjson',
            'equipment-alternate' => 'equipment-alternate.ndjson',
        };
        $bytes = file_get_contents(__DIR__.'/resources/'.$file);

        if (! is_string($bytes) || ! str_ends_with($bytes, "\n") || preg_match('//u', $bytes) !== 1) {
            throw new RuntimeException('Input is not complete UTF-8 NDJSON.');
        }

        return $bytes;
    }
}
