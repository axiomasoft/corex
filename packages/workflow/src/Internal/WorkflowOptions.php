<?php

declare(strict_types=1);

namespace CoreX\Workflow\Internal;

use CoreX\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type: read only by the provider and runtime checks. */
final readonly class WorkflowOptions
{
    public function __construct(
        public bool $enforceDeclarations,
    ) {}

    /** @param  array<array-key, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;

        $enforceDeclarations = $config['enforce_declarations'] ?? true;

        if (! is_bool($enforceDeclarations)) {
            $violations->add(path: 'enforce_declarations', message: 'must be a boolean');
            $enforceDeclarations = true;
        }

        $violations->failIfAny();

        return new self($enforceDeclarations);
    }

    /** @return list<string> config paths of enabled dangerous modes */
    public function dangerousModes(): array
    {
        return $this->enforceDeclarations ? [] : ['corex-workflow.enforce_declarations'];
    }
}
