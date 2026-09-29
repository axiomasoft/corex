<?php

declare(strict_types=1);

namespace CoreX\Access\Contracts;

use CoreX\Access\Enums\PrincipalType;

/**
 * A principal that can hold role assignments and be checked for capability —
 * a user, an AI agent (B-13), or an API key. Implemented by P2.20's
 * PrincipalIdentity; this contract is the public seam AutMatrixGrantSource
 * and AccessChecker resolve against.
 *
 * @internal spec: B-11 §3.3
 */
interface Principal
{
    public function principalType(): PrincipalType;

    public function principalId(): string;
}
