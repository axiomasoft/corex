<?php

declare(strict_types=1);

namespace CoreX\Enums;

/**
 * Primary-key strategy for BaseModel. ULID is the default (ADR-003).
 */
enum IdStrategy: string
{
    case Ulid = 'ulid';
    case Uuid = 'uuid';
}
