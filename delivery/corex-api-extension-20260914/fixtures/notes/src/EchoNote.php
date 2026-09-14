<?php

declare(strict_types=1);

namespace Example\Notes;

use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use CoreX\Api\Contracts\RequestContextProvider;
use LogicException;
use Override;

/** Installed-reuse fixture; no persistence and no business domain. */
final class EchoNote implements OperationHandler
{
    public function __construct(private readonly RequestContextProvider $contexts) {}

    #[Override]
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        if ($this->contexts->current() !== $context) {
            throw new LogicException('Context handoff differs.');
        }

        return ['title' => $input['title'], 'actor' => $context->principal()->id(), 'account' => $context->tenant()->account->id];
    }
}
