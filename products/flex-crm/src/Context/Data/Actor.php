<?php

declare(strict_types=1);

namespace Flex\Crm\Context\Data;

use Illuminate\Validation\ValidationException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class Actor
{
    public function __construct(public string $source, public string $id, public string $name)
    {
        if (! in_array($source, ['user', 'agent', 'api', 'workflow', 'import', 'system'], true) || trim($id) === '' || trim($name) === '') {
            throw ValidationException::withMessages(['actor' => 'A verified B-13 actor source, id and name are required.']);
        }
    }

    /** @return array{source: string, id: string, name: string} */
    public function toArray(): array
    {
        return ['source' => $this->source, 'id' => $this->id, 'name' => $this->name];
    }
}
