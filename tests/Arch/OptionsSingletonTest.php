<?php

declare(strict_types=1);

namespace CoreX\Tests\Arch;

use CoreX\Config\Data\CoreOptions;
use CoreX\Tests\Golden\GoldenTestCase;
use CoreX\Tests\Support\OptionsSingletonGate;
use stdClass;

// Options are lazy singletons: two make() calls return one instance.

final class OptionsSingletonTest extends GoldenTestCase
{
    public function test_every_options_class_is_a_shared_instance(): void
    {
        $classes = OptionsSingletonGate::optionsClasses(dirname(__DIR__, 2));

        self::assertContains(CoreOptions::class, $classes, 'Options discovery must include the kernel configuration.');
        self::assertSame([], OptionsSingletonGate::runtimeViolations($this->app, $classes));
    }

    public function test_no_package_source_binds_options_eagerly_or_per_resolution(): void
    {
        self::assertSame([], OptionsSingletonGate::bindingViolations(dirname(__DIR__, 2)));
    }

    public function test_a_bind_registration_makes_the_gate_red(): void
    {
        $this->app->bind(stdClass::class, static fn (): stdClass => new stdClass);

        $violations = OptionsSingletonGate::runtimeViolations($this->app, [stdClass::class]);

        self::assertSame(['stdClass is rebuilt on every make()'], $violations);
    }
}
