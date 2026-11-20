<?php

declare(strict_types=1);

namespace CoreX\Context\Internal;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\RequestGuard;
use Illuminate\Foundation\Application;
use Override;
use Packman\Attributes\Internal;

/** Every guard lookup in this frame resolves the independently verified identity. */
#[Internal]
final class WorkAuthManager extends AuthManager
{
    public function __construct(Application $app, private readonly RequestGuard $activeGuard)
    {
        parent::__construct($app);
    }

    #[Override]
    public function guard($name = null): RequestGuard
    {
        return $this->activeGuard;
    }
}
