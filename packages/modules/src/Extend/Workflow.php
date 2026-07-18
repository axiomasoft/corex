<?php

declare(strict_types=1);

namespace CoreX\Modules\Extend;

/**
 * 8. Workflow-builder step types and entity trigger events (B-10 §4.2,
 * critical for FlexCRM, B-12). `stepType`/`trigger` are pure declarations —
 * the ActionRegistry consuming them lives in flex/workflow, outside this
 * package.
 */
final class Workflow implements Extender
{
    /** @var 'stepType'|'trigger' */
    public string $kind;

    /** @var class-string|null */
    public ?string $stepClass = null;

    public ?string $entityHandle = null;

    /** @var list<string> */
    public array $eventNames = [];

    private function __construct(string $kind)
    {
        $this->kind = $kind;
    }

    /** @param  class-string  $stepClass */
    public static function stepType(string $stepClass): self
    {
        $workflow = new self('stepType');
        $workflow->stepClass = $stepClass;

        return $workflow;
    }

    /** @param  list<string>  $eventNames */
    public static function trigger(string $entityHandle, array $eventNames): self
    {
        $workflow = new self('trigger');
        $workflow->entityHandle = $entityHandle;
        $workflow->eventNames = $eventNames;

        return $workflow;
    }
}
