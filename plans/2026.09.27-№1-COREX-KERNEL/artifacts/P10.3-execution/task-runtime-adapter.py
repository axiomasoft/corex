#!/usr/bin/env python3
"""Use the compatible Task runtime with lossless P10.4 input parsing repairs.

The item specification and design audit fingerprint remain unchanged. The literal
P10.4 reference combines examples.md section 2 with a shell script mentioned in
that section. Task incorrectly applies section 2 to both quoted files. Split that
one declaration into the Markdown section and the complete shell script;
Inputs use middle-dot splitting so prose Laravel 12) cannot drop earlier entries; all file,
authority, route, readiness, digest and terminal checks still run in Task.
"""
from __future__ import annotations
import argparse
import hashlib
from pathlib import Path
import sys

PLAN = Path(__file__).resolve().parents[2]
SPEC = PLAN / 'phases/P10/P10.4.md'
ENTRY = '`plans/2026.09.27-№1-COREX-KERNEL/artifacts/P10-design/examples.md` (§2 — `scripts/ci-local.sh`, воспроизведение лейна в чистой копии)'
SPEC_SHA = '38d1694ffad31edb3ecc8e725e3ff9f3814daa4730d95accf39222d3a060f32f'


def install(runtime_root: Path) -> None:
    sys.path.insert(0, str(runtime_root / 'lib'))
    sys.path.insert(0, str(runtime_root.parents[1]))
    from task import plan_bundle
    if hashlib.sha256(SPEC.read_bytes()).hexdigest() != SPEC_SHA:
        raise ValueError('P10.4 specification changed; inspect the parsing repair before reuse')
    original = plan_bundle._raw_entries

    def entries(value: str, field: str) -> list[str]:
        result = []
        # Inputs use middle-dot separators; Laravel 12) is prose, not a numbered read.
        spec_inputs = next(line.removeprefix('**Inputs:** ') for line in SPEC.read_text().splitlines() if line.startswith('**Inputs:** '))
        selected = value.split(' · ') if field == 'Inputs' and value == spec_inputs else original(value, field)
        for entry in selected:
            if field == 'Inputs' and entry == ENTRY:
                result.extend([
                    '`plans/2026.09.27-№1-COREX-KERNEL/artifacts/P10-design/examples.md` (§2)',
                    '`scripts/ci-local.sh` (воспроизведение лейна в чистой копии)',
                ])
            else:
                result.append(entry)
        return result

    plan_bundle._raw_entries = entries


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--runtime-root', type=Path, required=True)
    parser.add_argument('operation', choices=('prepare', 'finalize', 'lint', 'delivery'))
    args, rest = parser.parse_known_args()
    install(args.runtime_root)
    if args.operation in {'prepare', 'finalize'}:
        from task import plan_work
        return plan_work.main([args.operation, *rest])
    if args.operation == 'lint':
        from task import plan_lint
        return plan_lint.main(rest)
    import runpy
    sys.argv = [str(args.runtime_root / 'scripts/plan-delivery.py'), *rest]
    runpy.run_path(sys.argv[0], run_name='__main__')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
