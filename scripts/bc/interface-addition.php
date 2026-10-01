<?php

declare(strict_types=1);

namespace Roave\BackwardCompatibility\DetectChanges\BCBreak\InterfaceBased;

use Roave\BackwardCompatibility\Change;
use Roave\BackwardCompatibility\Changes;
use Roave\BetterReflection\Reflection\ReflectionClass;

/** Api interfaces are callable ports, never external implementation contracts. */
final class MethodAdded implements InterfaceBased
{
    public function __invoke(ReflectionClass $fromInterface, ReflectionClass $toInterface): Changes
    {
        if ($fromInterface->getAttributesByName('CoreX\\Attributes\\Api') !== []) {
            return Changes::empty();
        }
        $old = array_fill_keys(array_map('strtolower', array_keys($fromInterface->getMethods())), true);
        $changes = [];
        foreach ($toInterface->getMethods() as $method) {
            if (! isset($old[strtolower($method->getName())])) {
                $changes[] = Change::added(sprintf('Method %s() was added to interface %s', $method->getName(), $fromInterface->getName()));
            }
        }

        return Changes::fromList(...$changes);
    }
}
