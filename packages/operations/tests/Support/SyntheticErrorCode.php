<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Support;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Packman\Errors\Contracts\ErrorCode;
use Packman\Errors\Contracts\ProblemDefinitionProvider;
use Packman\Errors\Contracts\ProblemRegistry;
use Packman\Errors\Data\ProblemDefinition;
use Packman\Errors\Enums\ErrorCategory;
use Packman\Errors\Laravel\ProblemDefinitions;
use Packman\Errors\Laravel\ProblemRegistryFactory;

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

    public static function registry(): ProblemRegistry
    {
        $provider = new class implements ProblemDefinitionProvider
        {
            public function definitions(): iterable
            {
                $statuses = [401, 403, 422, 409, 404, 429, 422, 503, 500];
                foreach (ErrorCategory::cases() as $index => $category) {
                    $code = SyntheticErrorCode::fromCategory($category);
                    yield new ProblemDefinition($code, 'urn:packman:problem:'.$code->value(), $statuses[$index], 'Safe failure.', $category === ErrorCategory::Unavailable, $category, ['reason']);
                }
            }
        };
        $previous = Container::getInstance();

        try {
            $app = new Application;
            $app->instance('config', new Repository);
            $app->instance($provider::class, $provider);
            ProblemDefinitions::register($app, __DIR__.'/synthetic-error-owner.json', $provider::class);

            return (new ProblemRegistryFactory)->make($app);
        } finally {
            Container::setInstance($previous);
        }
    }
}
