<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Workspace\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

/**
 * Thrown by {@see CoreX\Tenancy\Workspace\Internal\Models\Invitation::accept()} for a
 * revoked, already-accepted, or (persisted-or-lazily) expired invitation —
 * the domain-level counterpart of the HTTP 410 in B-11 §2.2/AC-19 (mapping
 * to an actual response is the route layer's concern, out of this item's
 * scope).
 */
#[Api(since: '1.0')]
final class InvitationNotAcceptableException extends RuntimeException {}
