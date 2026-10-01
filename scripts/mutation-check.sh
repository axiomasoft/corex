#!/usr/bin/env bash
# Mutation-check harness (P1.14, plan.md D45).
#
# Применяет патч-саботаж scripts/mutations/<item>-<case>.patch к рабочему
# дереву, гоняет ЗАРЕГИСТРИРОВАННУЮ для этого кейса тестовую команду и требует
# НЕнулевой exit-код (тест обязан покраснеть под саботажем — граница пинится).
# Патч всегда откатывается (успех или провал), рабочее дерево не остаётся мутированным.
#
# 3-way-фоллбэк (P2.19, D97): текстовые проходы по коду (докблоки и т.п.) сдвигают
# ТОЛЬКО контекст хунка, не саботаж — строгий `git apply --check` тогда отказывает,
# хотя граница цела. Строгий apply остаётся ПЕРВИЧНЫМ путём; при его отказе кейс
# пробует `git apply -3` (трёхсторонняя склейка от pre-image-blob патча) — успех
# печатает ГРОМКУЮ строку с именем кейса (гейт зелёный, но говорит «реанкорь»),
# отказ обеих схем — честное «граница сдвинулась», return 2. Откат зеркалит режим
# наложения и ДОКАЗЫВАЕТ (слепок хэшей таргет-путей до/после), что дерево вернулось
# в исходное состояние — расхождение печатает ГРОМКО и падает `exit 3`.
#
# Использование:
#   bash scripts/mutation-check.sh <item> <case>
#   bash scripts/mutation-check.sh --list
#   bash scripts/mutation-check.sh --all   (все зарегистрированные кейсы подряд, для CI-джоба, P3.7)
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

# Реестр кейсов: "<item>-<case>" -> тестовая команда, которая обязана
# ПОКРАСНЕТЬ под применённым патчем-саботажем.
declare -A TEST_CMD=(
    [P10-pg-outside-group]="PGSQL_PORT=1 vendor/bin/pest --exclude-group=pg packages/webhooks/tests/Feature/WebhookFanoutTest.php"
    [API-default-deny]="vendor/bin/pest --exclude-group=pg packages/operations/tests/Feature/PlatformTest.php --filter=test_default_authentication_and_authorization_deny"
    [API-module-activation]="vendor/bin/pest --exclude-group=pg packages/operations/tests/Feature/PlatformTest.php --filter=test_context_and_module_state_are_read_on_every_dispatch"
    [API-physical-context]="vendor/bin/pest --exclude-group=pg packages/operations/tests/Feature/PlatformTest.php --filter=test_missing_boxed_and_disabled_contexts_fail_closed"
    [API-undeclared-input]="vendor/bin/pest --exclude-group=pg packages/operations/tests/Feature/PlatformTest.php --filter=test_malformed_or_forged_input_is_rejected"
    [P1.14-pg-lane-split]="PGSQL_PORT=1 vendor/bin/pest --exclude-group=pg tests/Feature/PubSubDatabasePollTest.php"
    [P1.14-foreign-prefix-fp]="vendor/bin/pest tests/PHPStan/ModuleBoundaryRulesTest.php --filter=test_foreign_prefix_sql_no_false_positive"
    [P1.15-drop-entities]="vendor/bin/pest --exclude-group=pg tests/Feature/ModuleRegistryTest.php --filter='persists the entity|projects the compiled entities'"
    [P1.15-rename-check]="vendor/bin/pest --exclude-group=pg tests/Feature/ModuleRegistryTest.php --filter='leaves the destination intact'"
    [P1.15-silent-empty]="vendor/bin/pest --exclude-group=pg tests/Feature/ModuleRegistryTest.php --filter='fail-loud'"
    [P1.16-tags-back]="vendor/bin/pest --exclude-group=pg tests/Feature/ModuleRegistryTest.php --filter='non-taggable file store'"
    [P1.16-no-epoch-bump]="vendor/bin/pest --group=pg tests/Feature/ModuleLifecycleTest.php --filter='shared-DB epoch'"
    [P1.16-statereader-bare-binding]="vendor/bin/pest --group=pg tests/Feature/ModuleLifecycleTest.php --filter='resolves ModuleStateReader from the registered ModulesOptions connection'"
    [P1.17-tableprefix-skip]="vendor/bin/pest --exclude-group=pg tests/Feature/RegistryCompilerTest.php --filter='no tablePrefix'"
    [P1.17-stage-order-swap]="vendor/bin/pest --exclude-group=pg tests/Feature/RegistryCompilerTest.php --filter='AC-18'"
    [P1.18-purge-order]="vendor/bin/pest --group=pg tests/Feature/ModuleLifecycleTest.php --filter='reverse creation order'"
    [P1.18-enable-swallow]="vendor/bin/pest --group=pg tests/Feature/ModuleLifecycleTest.php --filter='all-or-nothing'"
    [P1.19-drop-precision]="vendor/bin/pest --group=pg tests/Feature/MigrationPublishTest.php --filter='precision'"
    [P1.19-hardcode-uuid]="vendor/bin/pest --group=pg tests/Feature/ConfigurableIdTest.php --filter='ulid id strategy'"
    [P1.20-idcolumns-uuid7-ulid]="vendor/bin/pest --group=pg tests/Feature/ConfigurableIdTest.php --filter='uuid7 -> uuid'"
    [P1.21-getattributes-create]="vendor/bin/pest --group=pg tests/Feature/AuditLogTest.php --filter='omits guarded'"
    [P1.21-killswitch]="vendor/bin/pest --group=pg tests/Feature/AuditLogTest.php --filter='observer isolation'"
    [P1.21-always-excluded-removed]="vendor/bin/pest --group=pg tests/Feature/AuditLogTest.php --filter='guarded = \[\] makes them fillable'"
    [P1.21-killswitch-writeseam]="vendor/bin/pest --group=pg tests/Feature/AuditLogTest.php --filter='write seam'"
    [P1.22-drop-unique-index]="vendor/bin/pest --group=pg tests/Feature/SettingsTest.php --filter='rejects a duplicate account-level row'"
    [P1.22-drop-workspace-branch]="vendor/bin/pest --group=pg tests/Feature/FeatureFlagsTest.php --filter='AC-22 \(a\)|AC-22 \(b\)'"
    [P1.23-stale-read]="vendor/bin/pest --group=pg tests/Feature/DepartmentTest.php --filter='re-read guard'"
    [P1.23-event-in-transaction]="vendor/bin/pest --group=pg tests/Feature/DepartmentTest.php --filter='after-commit'"
    [P1.23-move-workspace-filter]="vendor/bin/pest --group=pg tests/Feature/DepartmentTest.php --filter='never a same-path row'"
    [P1.24-bare-runninginconsole-guard]="vendor/bin/pest --exclude-group=pg tests/Feature/PubSubTest.php --filter='refuses a faked Octane runtime'"
    [P1.24-cursor-start-zero]="vendor/bin/pest --group=pg tests/Feature/PubSubDatabasePollTest.php --filter='does NOT replay pre-existing history'"
    [P1.25-domainevent-drop]="vendor/bin/pest --group=pg tests/Feature/ModuleLifecycleTest.php --filter='carries a UUIDv7'"
    [P1.25-null-provider-binding]="vendor/bin/pest --group=pg tests/Feature/SettingsTest.php --filter='resolves a manifest-declared DefaultSettingDefinition'"
    [P1.29-upgrade-swallow]="vendor/bin/pest --group=pg tests/Feature/ModuleLifecycleTest.php --filter='catch-up migration fails'"
    [P1.29-move-parent-workspace]="vendor/bin/pest --group=pg tests/Feature/DepartmentTest.php --filter='in ANOTHER workspace is rejected'"
)

if [ "${1:-}" = "--list" ]; then
    for key in "${!TEST_CMD[@]}"; do
        echo "$key"
    done | sort
    exit 0
fi

run_case() {
    local KEY="$1" PATCH CMD STATUS APPLY_MODE BEFORE_HASH AFTER_HASH
    PATCH="scripts/mutations/${KEY}.patch"

    if [ ! -f "$PATCH" ]; then
        echo "mutation-check: патч не найден: $PATCH" >&2
        return 2
    fi

    CMD="${TEST_CMD[$KEY]:-}"
    if [ -z "$CMD" ]; then
        echo "mutation-check: для кейса '$KEY' не зарегистрирована тестовая команда (см. TEST_CMD в этом скрипте)" >&2
        return 2
    fi

    local -a TARGETS=()
    while IFS= read -r line; do
        [ -n "$line" ] && TARGETS+=("$line")
    done < <(git apply --numstat "$PATCH" 2>/dev/null | awk '{print $3}')

    # Preserve exact dirty bytes and index entries, including failed 3-way attempts.
    local SNAPSHOT_DIR
    SNAPSHOT_DIR=$(mktemp -d)
    BEFORE_HASH=$(git hash-object -- "${TARGETS[@]}")
    git ls-files --stage -- "${TARGETS[@]}" > "$SNAPSHOT_DIR/index"
    for target in "${TARGETS[@]}"; do
        mkdir -p "$SNAPSHOT_DIR/$(dirname "$target")"
        cp -p -- "$target" "$SNAPSHOT_DIR/$target"
    done

    cleanup() {
        for target in "${TARGETS[@]}"; do
            cp -p -- "$SNAPSHOT_DIR/$target" "$target"
        done
        git update-index --index-info < "$SNAPSHOT_DIR/index"
        AFTER_HASH=$(git hash-object -- "${TARGETS[@]}")
        if [ "$AFTER_HASH" != "$BEFORE_HASH" ]; then
            echo "mutation-check[$KEY]: original bytes could not be restored" >&2
            exit 3
        fi
    }
    trap cleanup RETURN

    APPLY_MODE=strict
    if ! git apply --check --whitespace=nowarn "$PATCH" 2>/dev/null; then
        APPLY_MODE=3way
    fi
    if [ "$APPLY_MODE" = "3way" ]; then
        if ! git apply --whitespace=nowarn -3 "$PATCH" 2>/dev/null; then
            echo "mutation-check: patch $PATCH cannot apply; original bytes retained" >&2
            return 2
        fi
        echo "mutation-check[$KEY]: stale patch context; 3-way fallback applied, reanchor required" >&2
    else
        echo "mutation-check[$KEY]: применяю $PATCH"
        git apply --whitespace=nowarn "$PATCH"
    fi

    echo "mutation-check[$KEY]: гоняю: $CMD"
    set +e
    bash -c "$CMD" > "$SNAPSHOT_DIR/test.log" 2>&1
    STATUS=$?
    cat "$SNAPSHOT_DIR/test.log"
    set -e

    if rg -q 'No tests found|"tests"[[:space:]]*:[[:space:]]*0([,}])' "$SNAPSHOT_DIR/test.log"; then
        echo "mutation-check[$KEY]: FAIL — carrier executed no tests" >&2
        return 2
    fi

    if [ "$STATUS" -eq 0 ]; then
        echo "mutation-check[$KEY]: FAIL — тест остался зелёным под патчем-саботажем (граница НЕ пинится, exit=0)" >&2
        return 1
    fi

    echo "mutation-check[$KEY]: OK — тест покраснел под патчем-саботажем (exit=$STATUS), граница пинится"
}

if [ "${1:-}" = "--all" ]; then
    FAILED=()
    for key in $(for k in "${!TEST_CMD[@]}"; do echo "$k"; done | sort); do
        if ! run_case "$key"; then
            FAILED+=("$key")
        fi
    done
    if [ "${#FAILED[@]}" -gt 0 ]; then
        echo "mutation-check --all: ${#FAILED[@]} кейс(ов) НЕ покраснели/не применились: ${FAILED[*]}" >&2
        exit 1
    fi
    echo "mutation-check --all: все ${#TEST_CMD[@]} кейса(ов) покраснели под саботажем — OK"
    exit 0
fi

ITEM="${1:?usage: mutation-check.sh <item> <case>  (или --list, --all)}"
CASE="${2:?usage: mutation-check.sh <item> <case>  (или --list, --all)}"
run_case "${ITEM}-${CASE}"
