from pathlib import Path
import functools, hashlib
from task import plan_downstream, plan_lint

p=Path('plans/2026.09.19-№1-COREX-PLATFORM-HARDENING')
def snapshot():
    return {str(x):hashlib.sha256(x.read_bytes()).hexdigest()
            for x in p.rglob('*') if x.is_file() and '.plan-cache' not in x.parts and 'artifacts' not in x.parts}
before=snapshot()
# Cache a pure, repeatedly rebuilt graph for this stable invocation only.
plan_downstream._all_items=functools.lru_cache(maxsize=None)(plan_downstream._all_items)
status=plan_lint.main([str(p)])
assert before==snapshot(),'Plan inputs changed during lint'
raise SystemExit(status)
