<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Exceptions;

use RuntimeException;

/** @internal A fenced credential write must not be presented as authenticated. */
final class CredentialIssuanceDenied extends RuntimeException {}
