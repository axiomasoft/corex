<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use CoreX\Data\Data\JsonDocument;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class SearchDescription
{
    public function __construct(public JsonDocument $body, public string $witness) {}
}
