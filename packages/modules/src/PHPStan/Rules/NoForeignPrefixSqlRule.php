<?php

declare(strict_types=1);

namespace Flex\Modules\PHPStan\Rules;

use Flex\Modules\PHPStan\Rules\Support\DatabaseCall;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * B-10 §7.4 п.9: модуль не трогает таблицы чужого префикса напрямую — только
 * через контракты владельца.
 *
 * Владельца определяет конфиг `parameters.flexModules.modulePrefixes`
 * (PSR-4-неймспейс модуля → его `tablePrefix` из манифеста): статически связать
 * файл с манифестом нельзя, поэтому карта задаётся в neon пакета модуля. Код вне
 * карты (ядро, тесты, приложение) правило не трогает — ложных срабатываний нет.
 *
 * @implements Rule<CallLike>
 */
final class NoForeignPrefixSqlRule implements Rule
{
    /** Префиксы ядра — их не может трогать ни один модуль. */
    private const CORE_PREFIXES = ['sys', 'mod'];

    /**
     * @param  array<string, string>  $modulePrefixes  PSR-4-неймспейс модуля => tablePrefix
     */
    public function __construct(private readonly array $modulePrefixes) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    /**
     * @param  CallLike  $node
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $ownPrefix = $this->ownPrefix($scope);

        if ($ownPrefix === null || ! DatabaseCall::matches($node)) {
            return [];
        }

        $known = array_values(array_unique([...self::CORE_PREFIXES, ...array_values($this->modulePrefixes)]));
        $errors = [];

        foreach (DatabaseCall::constantStringArguments($node, $scope) as $sql) {
            foreach ($this->foreignTables($sql, $ownPrefix, $known) as $table) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Direct DB access to table "%s" of a foreign prefix (module owns "%s_") is forbidden (B-10 §7.4 п.9) — go through the owner\'s contract.',
                    $table,
                    $ownPrefix,
                ))
                    ->identifier('flexModules.foreignPrefixSql')
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @param  list<string>  $knownPrefixes
     * @return list<string>
     */
    private function foreignTables(string $sql, string $ownPrefix, array $knownPrefixes): array
    {
        if (preg_match_all('/\b([a-z][a-z0-9]{0,2})_[a-z0-9_]+\b/i', $sql, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $tables = [];

        foreach ($matches as [$table, $prefix]) {
            $prefix = strtolower($prefix);

            if ($prefix !== $ownPrefix && in_array($prefix, $knownPrefixes, true)) {
                $tables[] = $table;
            }
        }

        return array_values(array_unique($tables));
    }

    private function ownPrefix(Scope $scope): ?string
    {
        $namespace = $scope->getClassReflection()?->getName() ?? $scope->getNamespace();

        if ($namespace === null) {
            return null;
        }

        foreach ($this->modulePrefixes as $moduleNamespace => $prefix) {
            if (str_starts_with($namespace, rtrim($moduleNamespace, '\\').'\\')) {
                return $prefix;
            }
        }

        return null;
    }
}
