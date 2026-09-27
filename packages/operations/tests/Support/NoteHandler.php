<?php

declare(strict_types=1);

namespace CoreX\Api\Tests\Support;

use CoreX\Api\ApiError;
use CoreX\Api\Contracts\OperationHandler;
use CoreX\Api\Contracts\RequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class NoteHandler implements OperationHandler
{
    public static int $calls = 0;

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        match ($parameters['id'] ?? '') {
            'foreign' => throw new ModelNotFoundException('private table secret'),
            'conflict' => throw new ApiError('conflict', 409, 'Note already exists.'),
            'broken' => throw new RuntimeException('SQLSTATE secret_password private_table'),
            'hidden' => throw (new AuthorizationException('secret hidden'))->asNotFound(),
            'unauthorized' => throw new AuthorizationException('secret policy'),
            'unauthenticated' => throw new AuthenticationException('secret identity'),
            'invalid' => throw ValidationException::withMessages(['title' => ['Invalid title.']]),
            default => null,
        };

        return ['title' => $input['title'] ?? 'Note', 'id' => $parameters['id'] ?? 'new',
            'actor' => $context->principal()->id(), 'account' => $context->tenant()->account->id,
            'workspace' => $context->tenant()->workspace?->id];
    }
}
