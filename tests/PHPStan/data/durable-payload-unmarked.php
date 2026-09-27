<?php

declare(strict_types=1);

namespace CoreX\Tests\PHPStan\Data\DurablePayload;

use Illuminate\Database\Eloquent\Model;

final class NotAPayloadOnly
{
    public Model $user;
}
