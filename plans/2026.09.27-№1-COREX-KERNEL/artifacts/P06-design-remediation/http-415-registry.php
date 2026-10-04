<?php

declare(strict_types=1);

require getcwd().'/vendor/autoload.php';
use CoreX\Errors\Contracts\ErrorCode;
use CoreX\Errors\Contracts\ProblemDefinitionProvider;
use CoreX\Errors\Data\ProblemDefinition;
use CoreX\Errors\Enums\ErrorCategory;
use CoreX\Errors\Internal\CompiledProblemRegistry;
use CoreX\Errors\Internal\ProblemContribution;
use CoreX\Operations\Errors\Exceptions\ApiError;

enum PlannedOperationsCode: string implements ErrorCode
{
    case UnsupportedMediaType = 'operations.unsupported_media_type';

    public function value(): string
    {
        return $this->value;
    }
}
$definition = new ProblemDefinition(PlannedOperationsCode::UnsupportedMediaType, 'urn:corex:problem:operations.unsupported_media_type', 415, 'Unsupported media type.', false, ErrorCategory::Validation, []);
$provider = new class($definition) implements ProblemDefinitionProvider
{
    public function __construct(private ProblemDefinition $definition) {}

    public function definitions(): iterable
    {
        yield $this->definition;
    }
};

try {
    new CompiledProblemRegistry([new ProblemContribution('operations', $provider)]);
    echo "registry: accepted\n";
} catch (Throwable $error) {
    echo 'registry: '.get_class($error).': '.$error->getMessage()."\n";
}
$error = new ApiError(PlannedOperationsCode::UnsupportedMediaType, 415, 'A JSON request body is required.');
echo 'planned definition category: '.$definition->category->value."\n";
echo 'planned occurrence category: '.$error->errorDescriptor()->category->value."\n";

$registry = new CompiledProblemRegistry([new ProblemContribution('operations', $provider)]);

if ($registry->fromWire('operations.unsupported_media_type')->category !== $error->errorDescriptor()->category) {
    throw new RuntimeException('Registry/occurrence category mismatch');
}
