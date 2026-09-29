<?php

declare(strict_types=1);

namespace CoreX\Tests\Stubs;

use CoreX\Modules\Contracts\Extender;

/**
 * Implements the Extender marker but is NOT one of the closed 8 classes —
 * used to exercise RegistryCompiler's RFC-referral rejection (AC-19).
 */
final class StubRogueExtender implements Extender {}
