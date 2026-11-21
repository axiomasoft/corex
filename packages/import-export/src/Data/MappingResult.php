<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class MappingResult
{
    public function __construct(public string $status, public ?CanonicalRecord $record, public ?string $errorCode, public ?string $path = null)
    {
        if (! in_array($status, ['mapped', 'invalid', 'unsupported'], strict: true) || ($status === 'mapped') !== ($record !== null) || ($status === 'mapped') === ($errorCode !== null)) {
            throw new InvalidArgumentException('Mapping result is internally inconsistent.');
        }

        if ($path !== null && ($status === 'mapped' || strlen($path) > 512 || preg_match('/\A(?:\/(?:[^~\x00-\x1f\x7f]|~[01])*)*\z/Du', $path) !== 1)) {
            throw new InvalidArgumentException('Mapping error path must be an RFC 6901 pointer.');
        }

        if ($errorCode !== null) {
            Validation::opaque(value: $errorCode, name: 'mapping error code');
        }
    }
}
