<?php

declare(strict_types=1);

namespace CoreX\Operations\Authorization\Contracts;

interface Principal
{
    public function id(): string;
}
