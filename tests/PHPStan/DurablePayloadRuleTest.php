<?php

declare(strict_types=1);

namespace CoreX\Tests\PHPStan;

use CoreX\Messaging\PHPStan\Rules\DurablePayloadPromotedPropertyRule;
use CoreX\Messaging\PHPStan\Rules\DurablePayloadRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * Checks the DurablePayload marker and prohibited property types.
 *
 * @extends RuleTestCase<Rule>
 */
final class DurablePayloadRuleTest extends RuleTestCase
{
    private Rule $rule;

    protected function getRule(): Rule
    {
        return $this->rule;
    }

    public function test_declared_properties_on_marked_types(): void
    {
        $this->rule = new DurablePayloadRule;

        $fixture = __DIR__.'/data/durable-payload.php';
        $stub = __DIR__.'/data/tenant-context-contract-stub.php';

        $this->analyse([$stub, $fixture], [
            [
                'Durable payload CoreX\Tests\PHPStan\Data\DurablePayload\InvalidModelPayload::$user must not contain Illuminate\Database\Eloquent\Model',
                22,
            ],
            [
                'Durable payload CoreX\Tests\PHPStan\Data\DurablePayload\InvalidClosurePayload::$handler must not contain Closure',
                27,
            ],
            [
                'Durable payload CoreX\Tests\PHPStan\Data\DurablePayload\InvalidContainerPayload::$container must not contain Illuminate\Container\Container',
                32,
            ],
            [
                'Durable payload CoreX\Tests\PHPStan\Data\DurablePayload\InvalidTenantContextPayload::$tenant must not contain CoreX\Tenancy\Contracts\TenantContext',
                37,
            ],
            [
                'Durable payload property $missing declares an array with a missing value type; specify the element type.',
                42,
            ],
            [
                'Durable payload property $explicit declares an array with an explicit mixed value type; specify the element type.',
                48,
            ],
        ]);
    }

    public function test_unmarked_type_is_silent(): void
    {
        $this->rule = new DurablePayloadRule;

        $this->analyse([__DIR__.'/data/durable-payload-unmarked.php'], []);
    }

    public function test_promoted_property_on_marked_type(): void
    {
        $this->rule = new DurablePayloadPromotedPropertyRule;

        $fixture = __DIR__.'/data/durable-payload.php';
        $stub = __DIR__.'/data/tenant-context-contract-stub.php';

        $this->analyse([$stub, $fixture], [
            [
                'Durable payload CoreX\Tests\PHPStan\Data\DurablePayload\PromotedPayload::$ctx must not contain CoreX\Tenancy\Contracts\TenantContext',
                65,
            ],
            [
                'Durable payload property $missing declares an array with a missing value type; specify the element type.',
                72,
            ],
            [
                'Durable payload property $explicit declares an array with an explicit mixed value type; specify the element type.',
                80,
            ],
        ]);
    }
}
