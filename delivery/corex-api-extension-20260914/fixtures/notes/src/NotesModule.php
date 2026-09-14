<?php

declare(strict_types=1);

namespace Example\Notes;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Extend\Api;
use CoreX\Modules\Manifest;
use Override;

final class NotesModule extends CorexModule
{
    #[Override]
    public function manifest(): Manifest
    {
        return Manifest::make('example/notes')->title('Notes')->tablePrefix('nts')->extends([
            Api::operation(
                id: 'notes.echo', method: 'POST', path: '/notes', handler: EchoNote::class, ability: 'notes.echo',
                requestSchema: ['type' => 'object', 'required' => ['title'], 'properties' => ['title' => ['type' => 'string']]],
                responseSchema: ['type' => 'object'], rules: ['title' => ['required', 'string']],
            ),
        ]);
    }
}
