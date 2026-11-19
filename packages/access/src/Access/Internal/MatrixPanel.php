<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\Panels\PanelBuilder;
use AzGuard\Panels\PanelProvider;
use AzGuard\Policies\PolicyBinding;
use Packman\Attributes\Internal;

#[Internal]
final class MatrixPanel extends PanelProvider
{
    public static function getId(): string
    {
        return 'corex-matrix';
    }

    public function panel(PanelBuilder $panel): PanelBuilder
    {
        return $panel->for(MatrixSubject::class)->resourcePrefix(false)->permissions([MatrixPermission::class])
            ->policies([PolicyBinding::for(MatrixPermission::Evaluate, MatrixPolicy::class)]);
    }
}
