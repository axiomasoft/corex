<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class BulkOwnerRef
{
    public function __construct(public string $owner, public string $kind, public string $id)
    {
        if (preg_match('/\A[a-z0-9_.-]+\/[a-z0-9_.-]+\z/D', $owner) !== 1 || strlen($owner) > 191
            || preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $kind) !== 1 || preg_match('/\A[A-Za-z0-9_.:-]{1,128}\z/D', $id) !== 1) {
            throw new InvalidArgumentException('Invalid bulk owner reference.');
        }
    }
}
