<?php

declare(strict_types=1);

namespace Flex\Modules;

/**
 * Single extension point for a Flex module (B-10 §3.2): a concrete
 * subclass describes itself declaratively via manifest(), discovered from
 * `installed.json` (extra.corex.module) — no imperative registration.
 */
abstract class CorexModule
{
    abstract public function manifest(): Manifest;
}
