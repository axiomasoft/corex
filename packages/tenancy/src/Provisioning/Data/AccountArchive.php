<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

/** An opaque physical-owner archive reference; it is not itself proof of integrity. */
#[Api(since: '1.0')]
final readonly class AccountArchive
{
    /** @param list<non-empty-string> $formats Formats confirmed by the owner; empty when unspecified. */
    public function __construct(public string $reference, public int $sizeBytes, public array $formats = [])
    {
        if ($reference === '' || strlen($reference) > 512 || $sizeBytes < 1) {
            throw new InvalidArgumentException('An account archive needs an owner reference and a positive byte size.');
        }

        foreach ($formats as $format) {
            if (trim($format) === '') {
                throw new InvalidArgumentException('Archive formats must be non-empty strings confirmed by the owner.');
            }
        }
    }
}
