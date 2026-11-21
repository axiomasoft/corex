<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Completion\Internal;

use LogicException;
use Packman\Attributes\Internal;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Extenders\Workflow;

#[Internal]
final class TaskCompletionTrigger
{
    public const string MODULE = 'axiomasoft/corex-tasks';

    public const string ENTITY = 'tsk.task';

    public const string EVENT = 'completed';

    public static function assertInstalledDeclaration(ModuleDiscovery $discovery): void
    {
        foreach ($discovery->discover() as $manifest) {
            if ($manifest->composerName === self::MODULE
                && array_any($manifest->extenders, static fn (object $extender): bool => $extender instanceof Workflow
                    && $extender->kind === 'trigger'
                    && $extender->entityHandle === self::ENTITY
                    && in_array(self::EVENT, $extender->eventNames, true))) {
                return;
            }
        }

        throw new LogicException('Task workflow completion requires the installed tsk.task/completed trigger owned by Tasks.');
    }

    public static function isDeclared(ModuleRegistry $registry): bool
    {
        $triggerId = self::MODULE.':'.self::ENTITY.':'.self::EVENT;

        return array_any($registry->compiled()->extensions, static fn (array $extension): bool => ($extension['point'] ?? null) === 'workflow.trigger'
            && ($extension['id'] ?? null) === $triggerId
            && ($extension['owner'] ?? null) === self::MODULE);
    }

    public static function assertDeclared(ModuleRegistry $registry): void
    {
        if (! self::isDeclared($registry)) {
            throw new LogicException('Task workflow completion requires the compiled tsk.task/completed trigger owned by Tasks.');
        }
    }
}
