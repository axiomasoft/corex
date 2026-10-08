#!/usr/bin/env bash
# Reproduces artifacts/P07-design/facts/*.txt from the repository root. Read-only.
set -euo pipefail
root="$(git rev-parse --show-toplevel)"
out="${1:-$root/plans/2026.09.27-№1-COREX-KERNEL/artifacts/P07-design/facts}"
cd "$root"
mkdir -p "$out"

{
  echo "# base: $(git rev-parse HEAD)"
  echo "## files importing Stancl\\ (src/config/core)"
  grep -rlE 'Stancl\\' packages/*/src packages/*/config | sort
  echo "## files importing Stancl\\ (tests)"
  grep -rlE 'Stancl\\' packages/*/tests | sort
  echo "## symbol counts (src/config)"
  grep -rhoE 'Stancl\\[A-Za-z0-9\\]+' packages/*/src packages/*/config | sort | uniq -c | sort -rn
  echo "## non-PHP references (manifests, scripts, CI, docs)"
  grep -rlE --exclude-dir=__pycache__ 'stancl' composer.json packages/*/composer.json products/*/composer.json scripts .github docs 2>/dev/null | sort
  echo "## tenancy() helper callers"
  grep -rn 'tenancy()' packages/*/src | sort
} > "$out/stancl-coupling.txt"

{
  echo "# base: $(git rev-parse HEAD)"
  echo "## constructor parameters typed Connection|ConnectionInterface (captured connection), by package"
  grep -rnE '(private|public|protected)( readonly)? \??(ConnectionInterface|Connection) \$' packages/*/src | cut -d/ -f2 | sort | uniq -c
  echo "## total"
  grep -rnE '(private|public|protected)( readonly)? \??(ConnectionInterface|Connection) \$' packages/*/src | wc -l
  echo "## sites"
  grep -rnE '(private|public|protected)( readonly)? \??(ConnectionInterface|Connection) \$' packages/*/src | sort
} > "$out/connection-captures.txt"

{
  echo "# base: $(git rev-parse HEAD)"
  echo "## AzGuard\\ symbols in access src"
  grep -rhoE 'AzGuard\\[A-Za-z0-9\\]+' packages/access/src | sort | uniq -c | sort -rn
  echo "## files"
  grep -rlE 'AzGuard\\|azguard|az_guard' packages/access | sort
  echo "## other references"
  grep -rlE 'AzGuard|azguard|az_guard' composer.json packages/core/src docs scripts products 2>/dev/null | sort
} > "$out/azguard-coupling.txt"

{
  echo "# base: $(git rev-parse HEAD)"
  echo "## declared axiomasoft/corex-* edges (require | suggest), package-type"
  for f in packages/*/composer.json; do
    python3 - "$f" <<'PY'
import json, sys
f = sys.argv[1]
d = json.load(open(f))
req = [k for k in d.get('require', {}) if k.startswith('axiomasoft/corex-')]
sug = [k for k in d.get('suggest', {}) if k.startswith('axiomasoft/corex-')]
kind = d.get('extra', {}).get('corex', {}).get('package-type')
if req or sug:
    print(f"{d['name']}\t{kind}\trequire={','.join(req) or '-'}\tsuggest={','.join(sug) or '-'}")
PY
  done
  echo "## CoreX\\Wakeup imports outside packages/wakeup (src)"
  grep -rnE 'CoreX\\Wakeup\\' packages/*/src | grep -v '^packages/wakeup/' | sort
  echo "## writes to corex-wakeup config outside wakeup"
  grep -rn "corex-wakeup\." packages/*/src | grep -v '^packages/wakeup/' | sort
} > "$out/package-edges.txt"

{
  echo "# base: $(git rev-parse HEAD)"
  echo "## runtime-globals baseline (P05 D70 p.9)"
  cat scripts/runtime-globals-baseline.json
  echo "## facade/helper use in Impersonation"
  grep -rnE 'DB::|Auth::|session\(|event\(|now\(|tenancy\(|app\(' packages/tenancy/src/Impersonation | sort
} > "$out/impersonation.txt"

echo "facts written to $out"
