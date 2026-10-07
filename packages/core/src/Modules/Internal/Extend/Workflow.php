<?php

declare(strict_types=1);

namespace CoreX\Modules\Internal\Extend;

use CoreX\Modules\Contracts\Extender;

/**
 * 8. Workflow-builder step types and entity trigger events. `stepType`/
 * `trigger` are pure declarations — an external workflow package outside
 * `axiomasoft/corex` consumes them via the ActionRegistry.
 *
 * @internal spec: B-10 §4.2, B-12
 */
final class Workflow implements Extender
{
    /** @var class-string|null */
    public ?string $stepClass = null;

    public ?string $entityHandle = null;

    /** @var list<string> */
    public array $eventNames = [];

    /** @param 'stepType'|'trigger' $kind */
    private function __construct(public string $kind) {}

    /** @param  list<string>  $eventNames */
    public static function trigger(string $entityHandle, array $eventNames): self
    {
        $workflow = new self('trigger');
        $workflow->entityHandle = $entityHandle;
        $workflow->eventNames = $eventNames;

        return $workflow;
    }
}
