#!/usr/bin/env bash
# Each patch changes one owned behaviour. Its carrier must pass on the original
# tree and fail with the patch. Use --list, --all or a single case ID.
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

declare -A TEST_CMD=(
    [egress-skip-on-delivery]="vendor/bin/pest --exclude-group=pg packages/webhooks/tests/Feature/WebhookEgressPolicyTest.php --filter=revocation"
    [pg-outside-group]="PGSQL_PORT=1 vendor/bin/pest --exclude-group=pg packages/webhooks/tests/Feature/WebhookFanoutTest.php"
    [API-default-deny]="vendor/bin/pest --exclude-group=pg packages/operations/tests/Feature/PlatformTest.php --filter=test_default_authentication_and_authorization_deny"
    [API-module-activation]="vendor/bin/pest --exclude-group=pg packages/operations/tests/Feature/PlatformTest.php --filter=test_context_and_module_state_are_read_on_every_dispatch"
    [API-physical-context]="vendor/bin/pest --exclude-group=pg packages/operations/tests/Feature/PlatformTest.php --filter=test_missing_boxed_and_disabled_contexts_fail_closed"
    [API-undeclared-input]="vendor/bin/pest --exclude-group=pg packages/operations/tests/Feature/PlatformTest.php --filter=test_malformed_or_forged_input_is_rejected"
    [pg-lane-split]="PGSQL_PORT=1 vendor/bin/pest --exclude-group=pg tests/Unit/CapabilityScopeAxisTest.php tests/Feature/PubSubDatabasePollTest.php"
    [drop-precision]="vendor/bin/pest --group=pg tests/Feature/SchemaSetsPgTest.php --filter='precision'"
    [getattributes-create]="vendor/bin/pest --group=pg tests/Feature/AuditLogTest.php --filter='omits guarded'"
    [killswitch]="vendor/bin/pest --group=pg tests/Feature/AuditLogTest.php --filter='observer isolation'"
    [always-excluded-removed]="vendor/bin/pest --group=pg tests/Feature/AuditLogTest.php --filter='guarded = \[\] makes them fillable'"
    [killswitch-writeseam]="vendor/bin/pest --group=pg tests/Feature/AuditLogTest.php --filter='write seam'"
    [drop-unique-index]="vendor/bin/pest --group=pg tests/Feature/SettingsTest.php --filter='rejects a duplicate account-level row'"
    [drop-workspace-branch]="vendor/bin/pest --group=pg tests/Feature/FeatureFlagsTest.php --filter='user-override wins|department-override is independently reachable'"
    [stale-read]="vendor/bin/pest --group=pg tests/Feature/DepartmentTest.php --filter='soft-deleted department throws'"
    [event-in-transaction]="vendor/bin/pest --group=pg tests/Feature/DepartmentTest.php --filter='rolled back by an outer transaction'"
    [move-workspace-filter]="vendor/bin/pest --group=pg tests/Feature/DepartmentTest.php --filter='never a same-path row'"
    [bare-runninginconsole-guard]="vendor/bin/pest --exclude-group=pg tests/Feature/PubSubTest.php --filter='refuses a faked Octane runtime'"
    [cursor-start-zero]="vendor/bin/pest --group=pg tests/Feature/PubSubDatabasePollTest.php --filter='does NOT replay pre-existing history'"
    [null-provider-binding]="vendor/bin/pest --group=pg tests/Feature/SettingsTest.php --filter='resolves manifest setting defaults'"
    [move-parent-workspace]="vendor/bin/pest --group=pg tests/Feature/DepartmentTest.php --filter='in ANOTHER workspace is rejected'"
    [scoped-to-singleton]="vendor/bin/pest --exclude-group=pg tests/Feature/ResidentLifecycleTest.php"
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
        echo "mutation-check: missing patch: $PATCH" >&2
        return 2
    fi

    CMD="${TEST_CMD[$KEY]:-}"
    if [ -z "$CMD" ]; then
        echo "mutation-check: unknown case: $KEY" >&2
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

    # A broken environment or pre-existing failure cannot kill a mutant.
    if ! bash -c "$CMD" > "$SNAPSHOT_DIR/baseline.log" 2>&1; then
        echo "mutation-check[$KEY]: unmodified carrier failed" >&2
        cat "$SNAPSHOT_DIR/baseline.log" >&2
        return 2
    fi

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
        echo "mutation-check[$KEY]: applying $PATCH"
        git apply --whitespace=nowarn "$PATCH"
    fi

    echo "mutation-check[$KEY]: running $CMD"
    set +e
    bash -c "$CMD" > "$SNAPSHOT_DIR/test.log" 2>&1
    STATUS=$?
    cat "$SNAPSHOT_DIR/test.log"
    set -e

    # rg is not required. A missing grep or an unreadable log is exit 2, same as
    # a carrier that matched nothing (Pest prints "No tests found" and exits 0).
    local guard_status
    set +e
    command grep -Eq 'No tests found|"tests"[[:space:]]*:[[:space:]]*0([,}])' "$SNAPSHOT_DIR/test.log"
    guard_status=$?
    set -e
    if [ "$guard_status" -eq 0 ]; then
        echo "mutation-check[$KEY]: FAIL — carrier executed no tests" >&2
        return 2
    fi
    if [ "$guard_status" -ne 1 ]; then
        echo "mutation-check[$KEY]: FAIL — test-log guard failed (grep exit=$guard_status)" >&2
        return 2
    fi

    if [ "$STATUS" -eq 0 ]; then
        echo "mutation-check[$KEY]: FAIL — the mutant survived" >&2
        return 1
    fi

    echo "mutation-check[$KEY]: OK — the mutant was rejected (exit=$STATUS)"
}

if [ "${1:-}" = "--all" ]; then
    FAILED=()
    for key in $(for k in "${!TEST_CMD[@]}"; do echo "$k"; done | sort); do
        if ! run_case "$key"; then
            FAILED+=("$key")
        fi
    done
    if [ "${#FAILED[@]}" -gt 0 ]; then
        echo "mutation-check --all: ${#FAILED[@]} failed cases: ${FAILED[*]}" >&2
        exit 1
    fi
    echo "mutation-check --all: all ${#TEST_CMD[@]} mutants rejected"
    exit 0
fi

if [[ $# = 2 && $1 =~ ^P[0-9]+([.][0-9]+)?$ ]]; then
    # Historical callers used a planning prefix followed by the behavioural ID.
    run_case "$2"
elif [[ $# = 2 ]]; then
    run_case "$1-$2"
elif [[ $# = 1 ]]; then
    run_case "$1"
else
    echo 'usage: mutation-check.sh <case-id> | --list | --all' >&2
    exit 2
fi
