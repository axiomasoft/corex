<?php

declare(strict_types=1);

namespace CoreX\Tests\Support;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Foundation\Data\CleanupAction;
use CoreX\Foundation\Internal\LifecycleFrame;
use Illuminate\Container\Container;

/**
 * Package test fixture standing in for a confirmed purge plan: it opens the
 * same cleanup frame DatabaseLifecyclePlans opens around one owner's
 * cleanup() call, so an owner's re-checks can be driven without a full
 * module purge.
 */
final class ConfirmedCleanup
{
    /**
     * @template T
     *
     * @param  Closure(): T  $cleanup
     * @return T
     */
    public static function run(DataScope $scope, CleanupAction $action, Closure $cleanup): mixed
    {
        $frame = Container::getInstance()->make(LifecycleFrame::class);
        $frame->cleanup = [$scope, $action];
        $frame->leases = [];

        try {
            return $cleanup();
        } finally {
            $frame->cleanup = null;
        }
    }
}
