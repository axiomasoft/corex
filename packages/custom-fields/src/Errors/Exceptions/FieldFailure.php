<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Errors\Exceptions;

use CoreX\CustomFields\Errors\Enums\CustomFieldsErrorCode;
use Packman\Attributes\Api;
use Packman\Errors\Contracts\DescribesError;
use Packman\Errors\Data\ErrorDescriptor;
use RuntimeException;
use Throwable;

#[Api(since: '2.0.0')]
final class FieldFailure extends RuntimeException implements DescribesError
{
    public function __construct(private readonly CustomFieldsErrorCode $reason, private readonly ?string $path = null, ?Throwable $previous = null)
    {
        parent::__construct(str_replace('_', '-', $reason->value), previous: $previous);
    }

    public function errorDescriptor(): ErrorDescriptor
    {
        return new ErrorDescriptor($this->reason, $this->reason->category(), path: $this->path);
    }
}
