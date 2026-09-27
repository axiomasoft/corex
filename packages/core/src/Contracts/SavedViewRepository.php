<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\SavedViews\Data\SavedQuery;
use CoreX\SavedViews\Data\SavedViewRef;
use CoreX\SavedViews\Data\SaveViewInput;
use CoreX\SavedViews\Data\ViewContext;
use CoreX\SavedViews\Data\ViewScope;

interface SavedViewRepository
{
    /** @return list<SavedViewRef> */
    public function viewsFor(ViewContext $context, ViewScope $scope): array;

    public function save(ViewContext $context, SaveViewInput $input): SavedViewRef;

    public function delete(ViewContext $context, string $viewId): void;

    public function setDefault(ViewContext $context, string $viewId): SavedViewRef;

    public function compile(ViewContext $context, string $viewId): SavedQuery;
}
