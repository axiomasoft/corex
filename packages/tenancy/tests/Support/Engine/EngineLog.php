<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Tests\Support\Engine;

/** Records every bootstrap/revert of the engine into one shared, ordered log. */
final class EngineLog
{
    /** @var list<string> */
    public array $entries = [];
}
