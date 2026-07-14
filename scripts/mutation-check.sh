#!/usr/bin/env bash
# Mutation-check harness (P1.14, plan.md D45).
#
# Применяет патч-саботаж plans/COREX-CORE/mutations/<item>-<case>.patch к рабочему
# дереву, гоняет ЗАРЕГИСТРИРОВАННУЮ для этого кейса тестовую команду и требует
# НЕнулевой exit-код (тест обязан покраснеть под саботажем — граница пинится).
# Патч всегда откатывается (успех или провал), рабочее дерево не остаётся мутированным.
#
# Использование:
#   bash scripts/mutation-check.sh <item> <case>
#   bash scripts/mutation-check.sh --list
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

# Реестр кейсов: "<item>-<case>" -> тестовая команда, которая обязана
# ПОКРАСНЕТЬ под применённым патчем-саботажем.
declare -A TEST_CMD=(
    [P1.14-pg-lane-split]="PGSQL_PORT=1 vendor/bin/pest --exclude-group=pg tests/Feature/PubSubDatabasePollTest.php"
    [P1.14-foreign-prefix-fp]="vendor/bin/pest tests/PHPStan/ModuleBoundaryRulesTest.php --filter=test_foreign_prefix_sql_no_false_positive"
)

if [ "${1:-}" = "--list" ]; then
    for key in "${!TEST_CMD[@]}"; do
        echo "$key"
    done | sort
    exit 0
fi

ITEM="${1:?usage: mutation-check.sh <item> <case>  (или --list)}"
CASE="${2:?usage: mutation-check.sh <item> <case>  (или --list)}"
KEY="${ITEM}-${CASE}"
PATCH="plans/COREX-CORE/mutations/${KEY}.patch"

if [ ! -f "$PATCH" ]; then
    echo "mutation-check: патч не найден: $PATCH" >&2
    exit 2
fi

CMD="${TEST_CMD[$KEY]:-}"
if [ -z "$CMD" ]; then
    echo "mutation-check: для кейса '$KEY' не зарегистрирована тестовая команда (см. TEST_CMD в этом скрипте)" >&2
    exit 2
fi

if ! git apply --check --whitespace=nowarn "$PATCH" 2>/dev/null; then
    echo "mutation-check: патч $PATCH не применяется к текущему дереву (git apply --check провалился)" >&2
    exit 2
fi

cleanup() {
    git apply -R --whitespace=nowarn "$PATCH" 2>/dev/null || true
}
trap cleanup EXIT

echo "mutation-check[$KEY]: применяю $PATCH"
git apply --whitespace=nowarn "$PATCH"

echo "mutation-check[$KEY]: гоняю: $CMD"
set +e
bash -c "$CMD"
STATUS=$?
set -e

if [ "$STATUS" -eq 0 ]; then
    echo "mutation-check[$KEY]: FAIL — тест остался зелёным под патчем-саботажем (граница НЕ пинится, exit=0)" >&2
    exit 1
fi

echo "mutation-check[$KEY]: OK — тест покраснел под патчем-саботажем (exit=$STATUS), граница пинится"
