#!/usr/bin/env bash
# P05 design facts: reproducible inventory of config/composition/port debt.
# Usage (from repo root): bash "plans/2026.09.27-№1-COREX-KERNEL/artifacts/P05-design/scripts/p05-facts.sh" [out-dir]
# Reads only git-tracked files; writes TSV files and summary.txt into out-dir.
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"
out="${1:-plans/2026.09.27-№1-COREX-KERNEL/artifacts/P05-design/facts}"
mkdir -p "$out"

SRC='packages/*/src/*'
# Bridge = files allowed to touch the framework statically (D70 п.3).
BRIDGE='/Laravel/|ServiceProvider\.php:'
HELPERS='app|resolve|now|today|event|dispatch|session|request|auth|cache|logger|report|abort|url|route|base_path|storage_path|database_path|config_path|app_path'

tsv() { sed -E 's/^([^:]+):([0-9]+):[[:space:]]*/\1\t\2\t/'; }

# 1. Static Config holder: every reference outside the plan directory.
git grep -n -E '\bConfig::[a-zA-Z]+\(|CoreX\\Config\\Internal\\Config\b' -- packages products tests scripts \
  | grep -v -E 'Facades\\Config|AzGuardRoleModel' | tsv > "$out/static-config-refs.tsv" || true

# 2. config() helper outside providers (gate C1 scope, before allowlist).
git grep -n -E '(^|[^a-zA-Z_>:$\\])config\(' -- "$SRC" | grep -v 'ServiceProvider\.php:' \
  | grep -v -E ':[0-9]+:\s*(\*|//)' | tsv > "$out/config-helper-calls.tsv" || true

# 3. Config repository access by any other spelling (new gate C2 scope).
git grep -n -E "app\('config'\)|\[.config.\]|make\('config'\)|Contracts\\\\Config\\\\Repository|Illuminate\\\\Config\\\\Repository|Facades\\\\Config" -- "$SRC" \
  | tsv > "$out/config-repository-access.tsv" || true

# 4. Options inventory: config file, leaf-ish key count, existing Options classes.
{
  printf 'package\tconfig_file\tarrow_count\toptions_classes\n'
  for p in packages/*; do
    for c in "$p"/config/*.php; do
      [ -e "$c" ] || continue
      printf '%s\t%s\t%s\t%s\n' "${p#packages/}" "${c#"$p"/}" "$(grep -c '=>' "$c")" \
        "$(git ls-files "$p/src" | grep -E 'Options\.php$' | sed -E 's#.*/src/##' | paste -sd, -)"
    done
  done
} > "$out/options-inventory.tsv"

# 5. Providers: size and shape.
{
  printf 'file\tlines\tcontainer_calls\tclosures\tanon_classes\tpublic\n'
  for f in $(git ls-files "$SRC" | grep -E 'ServiceProvider\.php$'); do
    pkg="$(echo "$f" | cut -d/ -f1-2)"
    cls="$(basename "$f" .php)"
    public=no
    grep -q "\\\\\\\\$cls\"" "$pkg/composer.json" && public=yes
    printf '%s\t%s\t%s\t%s\t%s\t%s\n' "$f" "$(wc -l < "$f")" \
      "$(grep -c -E '\->(bind|bindIf|singleton|singletonIf|scoped|scopedIf|instance|extend|alias|tag)\(' "$f" || true)" \
      "$(grep -c -E 'function \(|fn \(' "$f" || true)" \
      "$(grep -c -E 'new (readonly )?class' "$f" || true)" "$public"
  done
} > "$out/providers.tsv"

# 6. Anonymous classes in src.
git grep -n -E 'new (readonly )?class' -- "$SRC" | tsv > "$out/anonymous-classes.tsv" || true

# 7. Facade imports outside the bridge.
git grep -n -E 'Illuminate\\Support\\Facades\\[A-Za-z]+' -- "$SRC" | grep -v -E "$BRIDGE" \
  | tsv > "$out/facades-outside-bridge.tsv" || true

# 8. Global helpers outside the bridge (comment lines dropped).
git grep -n -E "(^|[^a-zA-Z_>:\$\\\\])($HELPERS)\(" -- "$SRC" | grep -v -E "$BRIDGE" \
  | grep -v -E ':[0-9]+:\s*(\*|//|/\*)' | tsv > "$out/helpers-outside-bridge.tsv" || true

# 9. Static clock reads outside the bridge.
git grep -n -E 'Carbon(Immutable)?::(now|today|parse)\(|new \\?DateTimeImmutable\(\)|[^a-zA-Z_>:$]time\(\)' -- "$SRC" \
  | grep -v -E "$BRIDGE" | tsv > "$out/static-clock-outside-bridge.tsv" || true

# 10. Static mutable state.
git grep -n -E '^\s*(public|protected|private)\s+static\s+[^f]|^\s+static \$' -- "$SRC" \
  | grep -v 'static function' | tsv > "$out/static-mutable.tsv" || true

# 11. Tests that write corex/tenancy config (candidates for post-boot mutation).
git grep -n -E "config\(\[|config\(\)->set\(|\['config'\]->set\(|Config::set\(" -- 'packages/*/tests/*' 'tests/*' 'products/*/tests/*' \
  | grep -i -E 'corex|tenancy' | tsv > "$out/config-mutating-tests.tsv" || true

{
  echo "commit: $(git rev-parse HEAD)"
  for f in static-config-refs config-helper-calls config-repository-access anonymous-classes \
           facades-outside-bridge helpers-outside-bridge static-clock-outside-bridge static-mutable config-mutating-tests; do
    printf '%-32s rows=%s files=%s\n' "$f" "$(wc -l < "$out/$f.tsv")" "$(cut -f1 "$out/$f.tsv" | sort -u | wc -l)"
  done
  echo "-- helpers by name"
  grep -o -E "(^|[^a-zA-Z_>:\$\\\\])($HELPERS)\(" "$out/helpers-outside-bridge.tsv" | sed -E 's/.*[^a-z_]([a-z_]+)\($/\1/' | sort | uniq -c | sort -rn
  echo "-- helpers by package"
  cut -f1 "$out/helpers-outside-bridge.tsv" | cut -d/ -f2 | sort | uniq -c | sort -rn
  echo "-- facades by package"
  cut -f1 "$out/facades-outside-bridge.tsv" | cut -d/ -f2 | sort | uniq -c | sort -rn
  echo "-- facades by name"
  grep -o -E 'Facades\\[A-Za-z]+' "$out/facades-outside-bridge.tsv" | sort | uniq -c | sort -rn
  echo "-- static Config refs by area"
  cut -f1 "$out/static-config-refs.tsv" | awk -F/ '{print ($1=="packages" ? $1"/"$2"/"$3 : $1)}' | sort | uniq -c | sort -rn
  echo "-- tenancy Impersonation rows (P07 baseline)"
  cat "$out"/facades-outside-bridge.tsv "$out"/helpers-outside-bridge.tsv "$out"/static-clock-outside-bridge.tsv "$out"/static-mutable.tsv \
    | grep -c 'packages/tenancy/src/Impersonation/' || true
} > "$out/summary.txt"

cat "$out/summary.txt"
