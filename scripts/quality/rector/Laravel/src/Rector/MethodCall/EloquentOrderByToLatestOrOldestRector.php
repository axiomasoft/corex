<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\MethodCall;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use CoreX\Tooling\Rector\Laravel\NodeAnalyzer\QueryBuilderAnalyzer;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpParser\Node\Value\ValueResolver;
use Webmozart\Assert\Assert;

final class EloquentOrderByToLatestOrOldestRector extends AbstractRector implements ConfigurableRectorInterface
{
    public const string ALLOWED_PATTERNS = 'allowed_patterns';

    /**
     * @var string[]
     */
    private array $allowedPatterns = ['*_at', '*_date', '*_on'];

    public function __construct(private readonly QueryBuilderAnalyzer $queryBuilderAnalyzer, private readonly ValueResolver $valueResolver) {}

    public function getNodeTypes(): array
    {
        return [MethodCall::class];
    }

    /**/
    public function refactor(Node $node): ?Node
    {
        if (! $node instanceof MethodCall) {
            return null;
        }

        if (! $this->isOrderByMethodCall($node)) {
            return null;
        }
        $columnArg = $node->getArg('column', 0);
        $directionArg = $node->getArg('direction', 1);

        if ($columnArg === null) {
            return null;
        }

        if (! $this->isAllowedPattern($columnArg->value)) {
            return null;
        }
        $direction = $directionArg === null ? 'asc' : $this->valueResolver->getValue($directionArg);

        if (! is_string($direction)) {
            return null;
        }

        return $this->convertOrderByToLatest($node, $columnArg, $direction);
    }

    /**
     * @param  mixed[]  $configuration
     */
    public function configure(array $configuration): void
    {
        $allowedPatterns = $configuration[self::ALLOWED_PATTERNS] ?? [];
        Assert::isArray($allowedPatterns);
        Assert::allString($allowedPatterns);
        $this->allowedPatterns = $allowedPatterns;
    }

    private function isOrderByMethodCall(MethodCall $methodCall): bool
    {
        return $this->queryBuilderAnalyzer->isMatchingCall($methodCall, 'orderBy') || $this->queryBuilderAnalyzer->isMatchingCall($methodCall, 'orderByDesc');
    }

    private function isAllowedPattern(Expr $expr): bool
    {
        // If no patterns are specified, consider all column names as matching
        if ($this->allowedPatterns === []) {
            return true;
        }
        $value = $this->valueResolver->getValue($expr);

        if (is_string($value)) {
            foreach ($this->allowedPatterns as $allowedPattern) {
                if (fnmatch($allowedPattern, $value)) {
                    return true;
                }
            }
        }

        if ($expr instanceof Variable && is_string($expr->name)) {
            foreach ($this->allowedPatterns as $allowedPattern) {
                if (fnmatch(ltrim($allowedPattern, '$'), $expr->name)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function convertOrderByToLatest(MethodCall $methodCall, Arg $columnArg, string $direction): MethodCall
    {
        if ($this->isName($methodCall->name, 'orderByDesc')) {
            $method = 'latest';
        } else {
            $method = strtolower($direction) === 'asc' ? 'oldest' : 'latest';
        }
        $methodCall->name = new Identifier($method);
        $methodCall->args = $this->valueResolver->isValue($columnArg->value, 'created_at') ? [] : [$columnArg];

        return $methodCall;
    }
}
