<?php

declare(strict_types=1);

namespace CoreX\Panels\SavedViews\Contracts;

use CoreX\Panels\SavedViews\Data\SavedQuery;
use CoreX\Panels\SavedViews\Data\SavedViewRef;
use CoreX\Panels\SavedViews\Data\SaveViewInput;
use CoreX\Panels\SavedViews\Data\ViewContext;
use CoreX\Panels\SavedViews\Data\ViewScope;

interface SavedViewRepository
{
    /** @return list<SavedViewRef> */
    public function viewsFor(ViewContext $context, ViewScope $scope): array;

    public function save(ViewContext $context, SaveViewInput $input): SavedViewRef;

    public function delete(ViewContext $context, string $viewId): void;

    public function setDefault(ViewContext $context, string $viewId): SavedViewRef;

    public function compile(ViewContext $context, string $viewId): SavedQuery;
}
