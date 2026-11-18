<?php

declare(strict_types=1);

namespace CoreX\Panels\Laravel;

use CoreX\Context\Enums\PrincipalType;
use CoreX\Invocation\Contracts\RequestContext;
use InvalidArgumentException;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Panels\Data\Panel\PanelContext;
use Packman\Panels\SavedViews\Data\ViewContext;

/** Projects a trusted user invocation without accepting identity from request input. */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final readonly class PanelContexts
{
    public static function forUser(RequestContext $request): PanelContext
    {
        return new PanelContext(
            tenant: $request->tenant(),
            actorKind: 'user',
            actorId: self::userId($request),
        );
    }

    public static function savedViewsForUser(RequestContext $request): ViewContext
    {
        return new ViewContext(
            tenant: $request->tenant(),
            actorKind: 'user',
            actorId: self::userId($request),
        );
    }

    private static function userId(RequestContext $request): string
    {
        $principal = $request->principal();

        if ($principal->principalType() !== PrincipalType::User) {
            throw new InvalidArgumentException('User panel contexts require an authenticated user principal.');
        }

        return $principal->principalId();
    }
}
