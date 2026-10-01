<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Support;

use CoreX\Errors\Contracts\ErrorCode;
use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Internal\CompiledProblemRegistry;
use CoreX\Errors\Internal\ProblemContribution;

enum SyntheticErrorCode: string implements ErrorCode
{
    case Authentication = 'synthetic.authentication';
    case Authorization = 'synthetic.authorization';
    case Validation = 'synthetic.validation';
    case Conflict = 'synthetic.conflict';
    case NotFound = 'synthetic.not_found';
    case RateLimit = 'synthetic.rate_limit';
    case Unsupported = 'synthetic.unsupported';
    case Unavailable = 'synthetic.unavailable';
    case Internal = 'synthetic.internal';

    public function value(): string
    {
        return $this->value;
    }

    public static function fromCategory(ErrorCategory $category): self
    {
        return self::from('synthetic.'.$category->value);
    }

    public static function registry(): CompiledProblemRegistry
    {
        return new CompiledProblemRegistry([new ProblemContribution('synthetic', new class implements ProblemDefinitionProvider
        {
            public function definitions(): iterable
            {
                $statuses = [401, 403, 422, 409, 404, 429, 422, 503, 500];
                foreach (ErrorCategory::cases() as $index => $category) {
                    $code = SyntheticErrorCode::fromCategory($category);
                    yield new ProblemDefinition($code, 'urn:corex:problem:'.$code->value(), $statuses[$index], 'Safe failure.', $category === ErrorCategory::Unavailable, $category, ['reason']);
                }
            }
        })]);
    }
}
