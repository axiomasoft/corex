<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

enum IndexPolicy: string
{
    case None = 'none';
    case ExpressionBtree = 'expression_btree';
    case Promoted = 'promoted';
}
