<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
enum IndexPolicy: string
{
    case None = 'none';
    case ExpressionBtree = 'expression_btree';
    case Promoted = 'promoted';
}
