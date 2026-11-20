<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use DateTimeImmutable;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class OperationKey
{
    public function __construct(public string $id, public int $attempt, public ?string $effectKey = null, public ?DateTimeImmutable $deadline = null)
    {
        if ($id === '' || strlen($id) > 191 || $attempt < 1 || ($effectKey !== null && ($effectKey === '' || strlen($effectKey) > 191))) {
            throw new InvalidArgumentException('Invalid operation attempt.');
        }
    }
}
