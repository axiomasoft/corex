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
    [P1.15-drop-entities]="vendor/bin/pest --exclude-group=pg tests/Feature/ModuleRegistryTest.php --filter='persists the entity|projects the compiled entities'"
    [P1.15-rename-check]="vendor/bin/pest --exclude-group=pg tests/Feature/ModuleRegistryTest.php --filter='leaves the destination intact'"
    [P1.15-silent-empty]="vendor/bin/pest --exclude-group=pg tests/Feature/ModuleRegistryTest.php --filter='fail-loud'"
    [P1.16-tags-back]="vendor/bin/pest --exclude-group=pg tests/Feature/ModuleRegistryTest.php --filter='non-taggable file store'"
    [P1.16-no-epoch-bump]="vendor/bin/pest --group=pg tests/Feature/ModuleLifecycleTest.php --filter='shared-DB epoch'"
    [P1.17-tableprefix-skip]="vendor/bin/pest --exclude-group=pg tests/Feature/RegistryCompilerTest.php --filter='no tablePrefix'"
    [P1.17-stage-order-swap]="vendor/bin/pest --exclude-group=pg tests/Feature/RegistryCompilerTest.php --filter='AC-18'"
    [P1.18-purge-order]="vendor/bin/pest --group=pg tests/Feature/ModuleLifecycleTest.php --filter='reverse creation order'"
    [P1.18-enable-swallow]="vendor/bin/pest --group=pg tests/Feature/ModuleLifecycleTest.php --filter='all-or-nothing'"
    [P1.19-drop-precision]="vendor/bin/pest --group=pg tests/Feature/MigrationPublishTest.php --filter='precision'"
    [P1.19-hardcode-uuid]="vendor/bin/pest --group=pg tests/Feature/ConfigurableIdTest.php --filter='ulid id strategy'"
    [P1.20-idcolumns-uuid7-ulid]="vendor/bin/pest --group=pg tests/Feature/ConfigurableIdTest.php --filter='uuid7 -> uuid'"
    [P1.21-getattributes-create]="vendor/bin/pest --group=pg tests/Feature/AuditLogTest.php --filter='omits guarded'"
    [P1.21-killswitch]="vendor/bin/pest --group=pg tests/Feature/AuditLogTest.php --filter='killswitch silences'"
    [P1.22-drop-unique-index]="vendor/bin/pest --group=pg tests/Feature/SettingsTest.php --filter='rejects a duplicate account-level row'"
    [P1.22-drop-workspace-branch]="vendor/bin/pest --group=pg tests/Feature/FeatureFlagsTest.php --filter='AC-22 \(a\)|AC-22 \(b\)'"
    [P1.23-stale-read]="vendor/bin/pest --group=pg tests/Feature/DepartmentTest.php --filter='re-read guard'"
    [P1.23-event-in-transaction]="vendor/bin/pest --group=pg tests/Feature/DepartmentTest.php --filter='after-commit'"
    [P1.24-bare-runninginconsole-guard]="vendor/bin/pest --exclude-group=pg tests/Feature/PubSubTest.php --filter='refuses a faked Octane runtime'"
    [P1.24-cursor-start-zero]="vendor/bin/pest --group=pg tests/Feature/PubSubDatabasePollTest.php --filter='does NOT replay pre-existing history'"
    [P1.25-domainevent-drop]="vendor/bin/pest --group=pg tests/Feature/ModuleLifecycleTest.php --filter='carries a UUIDv7'"
    [P1.25-null-provider-binding]="vendor/bin/pest --group=pg tests/Feature/SettingsTest.php --filter='resolves a manifest-declared SettingDefault'"
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
