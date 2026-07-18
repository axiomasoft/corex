<?php

declare(strict_types=1);

namespace CoreX\Modules\Extend;

/**
 * 3. REST contract (B-10 §4.2): either auto-CRUD for an owned entity, or an
 * added attribute on ANOTHER module's resource (narrow analogue of Flarum
 * ApiSerializer — no controller override, §4.3 п.3).
 */
final class Api implements Extender
{
    public string $entityHandle;

    /** @var 'entity'|'extendResource' */
    public string $mode;

    public bool $isReadonly = false;

    /** @var list<string>|null */
    public ?array $onlyFields = null;

    /** @var list<string> */
    public array $includes = [];

    /** @var array<string, class-string> attribute name => class-string<ApiAttributeResolver> */
    public array $attributes = [];

    private function __construct()
    {
        //
    }

    public static function entity(string $entityHandle): self
    {
        $api = new self;
        $api->entityHandle = $entityHandle;
        $api->mode = 'entity';

        return $api;
    }

    public static function extendResource(string $entityHandle): self
    {
        $api = new self;
        $api->entityHandle = $entityHandle;
        $api->mode = 'extendResource';

        return $api;
    }

    public function readonly(): self
    {
        $this->isReadonly = true;

        return $this;
    }

    /** @param  list<string>  $only */
    public function fields(array $only): self
    {
        $this->onlyFields = $only;

        return $this;
    }

    public function include(string ...$relations): self
    {
        $this->includes = array_values($relations);

        return $this;
    }

    /** @param  class-string  $resolverClass */
    public function attribute(string $name, string $resolverClass): self
    {
        $this->attributes[$name] = $resolverClass;

        return $this;
    }
}
