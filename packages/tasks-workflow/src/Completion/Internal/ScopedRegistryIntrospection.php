<?php

declare(strict_types=1);

namespace CoreX\TasksWorkflow\Completion\Internal;

use Illuminate\Foundation\Application;
use Override;
use Packman\Attributes\Internal;
use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Registry\RegistryIntrospection;
use Packman\Modules\Data\Registry\ModuleExplanation;
use Packman\Modules\Data\Registry\RegistryMap;

#[Internal]
final readonly class ScopedRegistryIntrospection implements RegistryIntrospection
{
    public function __construct(private RegistryIntrospection $reader, private CompletionScope $scope, private bool $booted) {}

    #[Override]
    public function extensionPoints(): RegistryMap
    {
        return $this->currentReader()->extensionPoints();
    }

    #[Override]
    public function why(string $module, ?ContextScopeInterface $context): ModuleExplanation
    {
        return $this->currentReader()->why($module, $context);
    }

    private function currentReader(): RegistryIntrospection
    {
        $app = Application::getInstance();

        // The native reader captures the gate chain at construction. Provider completion
        // and scope resets both require a new delegate from the current public factory.
        return $this->booted === $app->isBooted() && $app->make(CompletionScope::class) === $this->scope
            ? $this->reader
            : $app->make(RegistryIntrospection::class);
    }
}
