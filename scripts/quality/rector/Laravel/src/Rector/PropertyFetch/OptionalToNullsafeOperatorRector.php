<?php

declare(strict_types=1);

namespace CoreX\Tooling\Rector\Laravel\Rector\PropertyFetch;

use CoreX\Tooling\Rector\Laravel\AbstractRector;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Scalar;
use Rector\Contract\Rector\ConfigurableRectorInterface;
use Rector\PhpParser\Node\Value\ValueResolver;
use Rector\ValueObject\PhpVersion;
use Rector\VersionBonding\Contract\MinPhpVersionInterface;
use Webmozart\Assert\Assert;

/**
 * @changelog https://github.com/laravel/laravel/pull/5670
 * @changelog https://github.com/laravel/framework/pull/38868
 * @changelog https://wiki.php.net/rfc/nullsafe_operator
 */
final class OptionalToNullsafeOperatorRector extends AbstractRector implements ConfigurableRectorInterface, MinPhpVersionInterface
{
    public const string EXCLUDE_METHODS = 'exclude_methods';

    /**
     * @var array<class-string<Expr>>
     */
    private const array SKIP_VALUE_TYPES = [ConstFetch::class, Scalar::class, Array_::class, ClassConstFetch::class];

    /**
     * @var string[]
     */
    private array $excludeMethods = [];

    public function __construct(private readonly ValueResolver $valueResolver) {}

    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes(): array
    {
        return [PropertyFetch::class, MethodCall::class];
    }

    public function refactor(Node $node): ?Node
    {
        if (! ($node instanceof PropertyFetch || $node instanceof MethodCall)) {
            return null;
        }

        if (! $node->var instanceof FuncCall) {
            return null;
        }

        if (! $this->isName($node->var->name, 'optional')) {
            return null;
        }

        // exclude macro methods
        if ($node instanceof MethodCall && $this->isNames($node->name, $this->excludeMethods)) {
            return null;
        }

        if (! isset($node->var->args[0])) {
            return null;
        }

        // skip if the second arg exists and not null
        if ($this->hasCallback($node->var)) {
            return null;
        }
        /** @var Arg $firstArg */
        $firstArg = $node->var->args[0];

        // skip if the first arg cannot be used as variable directly
        if ($this->shouldSkipFirstArg($firstArg->value)) {
            return null;
        }

        if ($node instanceof PropertyFetch) {
            return new NullsafePropertyFetch($firstArg->value, $node->name);
        }

        return new NullsafeMethodCall($firstArg->value, $node->name, $node->args);
    }

    /**
     * @return PhpVersion::PHP_80
     */
    public function provideMinPhpVersion(): int
    {
        return PhpVersion::PHP_80;
    }

    /**
     * @param  mixed[]  $configuration
     */
    public function configure(array $configuration): void
    {
        $excludeMethods = $configuration[self::EXCLUDE_METHODS] ?? $configuration;
        Assert::isArray($excludeMethods);
        Assert::allString($excludeMethods);
        $this->excludeMethods = $excludeMethods;
    }

    private function hasCallback(FuncCall $funcCall): bool
    {
        return isset($funcCall->args[1]) && $funcCall->args[1] instanceof Arg && ! $this->valueResolver->isNull($funcCall->args[1]->value);
    }

    private function shouldSkipFirstArg(Expr $expr): bool
    {
        return array_any(self::SKIP_VALUE_TYPES, fn ($type) => $expr instanceof $type);
    }
}
