<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use CoreX\Attributes\Api;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class SourceManifest
{
    public function __construct(public string $immutableRef, public string $sha256, public int $length, public string $format, public string $parserVersion, public string $encoding, public string $delimiter, public string $enclosure, public string $escape, public string $schemaHash)
    {
        Validation::opaque(value: $immutableRef, name: 'immutable source reference');
        Validation::sha256(value: $sha256, name: 'source hash');
        Validation::sha256(value: $schemaHash, name: 'schema hash');

        if ($length < 0 || $length > 1_073_741_824 || ! in_array($format, ['csv', 'ndjson'], strict: true) || $parserVersion === '' || ! in_array($encoding, ['UTF-8'], strict: true) || strlen($delimiter) !== 1 || strlen($enclosure) !== 1 || ! in_array($escape, ['', '\\'], strict: true)) {
            throw new InvalidArgumentException('Invalid bounded source manifest.');
        }
    }
}
