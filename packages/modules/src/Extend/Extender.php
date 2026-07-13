<?php

declare(strict_types=1);

namespace Flex\Modules\Extend;

/**
 * Marker interface for the closed set of 8 extenders (B-10 §4.2). A third
 * party class implementing this outside the set is rejected by
 * RegistryCompiler with a reference to the RFC process (§4.3, AC-19).
 */
interface Extender {}
