<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class IndexReceipt
{
    public function __construct(public string $taskId)
    {
        if ($taskId === '' || strlen($taskId) > 255 || preg_match('/[\x00-\x1f\x7f]/', $taskId) === 1) {
            throw new InvalidArgumentException('Invalid provider task reference.');
        }
    }
}
