<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Exceptions;

use DomainException;

final class InvalidPlanningDraft extends DomainException
{
    public static function forField(string $field, string $message): self
    {
        return new self("{$field}: {$message}");
    }
}
