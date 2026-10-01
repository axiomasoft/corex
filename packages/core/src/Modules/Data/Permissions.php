<?php

declare(strict_types=1);

namespace CoreX\Modules\Data;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Modules\Contracts\Extender;

/**
 * 6. AzGuard permission group `{panel}.{entity}.{action}`, claimed into
 * mod_records on enable. `scopeable()` opts into the aut_role_matrix scope
 * axis (none/own/dept/dept_tree/workspace/account).
 */
#[Spi(kind: SpiKind::Call, since: '1.0')]
final class Permissions implements Extender
{
    public string $entity;

    public string $panel;

    /** @var list<string> */
    public array $actions;

    public bool $isScopeable = false;

    /** @param  list<string>  $actions  private constructor — build via group(). */
    private function __construct(string $entity, array $actions, string $panel)
    {
        $this->entity = $entity;
        $this->actions = $actions;
        $this->panel = $panel;
    }

    /** @param  list<string>  $actions */
    public static function group(string $entity, array $actions, string $panel = 'app'): self
    {
        return new self($entity, $actions, $panel);
    }

    public function scopeable(): self
    {
        $this->isScopeable = true;

        return $this;
    }
}
