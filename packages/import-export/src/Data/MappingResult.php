<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class MappingResult
{
    public function __construct(public string $status, public ?CanonicalRecord $record, public ?string $errorCode)
    {
        if (! in_array($status, ['mapped', 'invalid', 'unsupported'], strict: true) || ($status === 'mapped') !== ($record !== null) || ($status === 'mapped') === ($errorCode !== null)) {
            throw new InvalidArgumentException('Mapping result is internally inconsistent.');
        }

        if ($errorCode !== null) {
            Validation::opaque(value: $errorCode, name: 'mapping error code');
        }
    }
}
