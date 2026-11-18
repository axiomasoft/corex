<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use Illuminate\Http\Request;
use Packman\Attributes\Internal;

/** @internal */
#[Internal]
interface InputEnvelopeReader
{
    /** @return array<array-key, mixed> */
    public function read(Request $request): array;
}
