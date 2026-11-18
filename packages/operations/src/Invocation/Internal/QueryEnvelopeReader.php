<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use Illuminate\Http\Request;
use Override;
use Packman\Attributes\Internal;

/** @internal */
#[Internal]
final class QueryEnvelopeReader implements InputEnvelopeReader
{
    #[Override]
    public function read(Request $request): array
    {
        return $request->query->all();
    }
}
