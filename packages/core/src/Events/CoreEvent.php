<?php

declare(strict_types=1);

namespace CoreX\Events;

/**
 * Base class for ecosystem domain events. Extension across modules happens via
 * events/observers only — monkey-patching is forbidden.
 */
abstract class CoreEvent {}
