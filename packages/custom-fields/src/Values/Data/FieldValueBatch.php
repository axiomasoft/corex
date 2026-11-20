<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Values\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldValueBatch
{
    /**
     * @param  list<FieldValueSnapshot>  $items
     * @param  list<array{position:int,reason:string}>  $rejections
     */
    public function __construct(public array $items, public array $rejections) {}
}
