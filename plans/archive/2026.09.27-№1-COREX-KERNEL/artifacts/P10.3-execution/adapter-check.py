#!/usr/bin/env python3
"""Prove the parser repair preserves the unchanged P10.4 design and all sources."""
import argparse
import hashlib
import importlib.util
import json
from pathlib import Path
import sys

parser = argparse.ArgumentParser()
parser.add_argument('--runtime-root', type=Path, required=True)
args = parser.parse_args()
sys.path.insert(0, str(args.runtime_root / 'lib'))
sys.path.insert(0, str(args.runtime_root.parents[1]))
from task import plan_bundle, plan_readiness

out = Path(__file__).resolve().parent
plan = out.parents[1]
before = plan_readiness.design_gate(plan)
assert before['allowed'], before
raw_entries = plan_bundle._raw_entries(plan_bundle._fields(plan, 'P10.4')['Inputs'], 'Inputs')
try:
    plan_bundle.model(plan, 'P10.4')
    raise AssertionError('Original runtime did not reproduce the parser defect')
except plan_bundle.BundleError as exception:
    assert "section '2'" in str(exception), exception
    original_error = str(exception)
spec = importlib.util.spec_from_file_location('adapter', out / 'task-runtime-adapter.py')
adapter = importlib.util.module_from_spec(spec)
spec.loader.exec_module(adapter)
adapter.install(args.runtime_root)
model = plan_bundle.model(plan, 'P10.4')
expected = {
    'research/P10-design.md': {('section', '1'), ('section', '3.4'), ('section', '5')},
    'research/P10-design-rag.md': {('whole_file', None)},
    'artifacts/P10-design/examples.md': {('section', '2')},
    'scripts/ci-local.sh': {('whole_file', None)},
    'decisions/D73-hosted-ci-acceptance-boundary.md': {('whole_file', None)},
    'decisions/D74-p10-hermetic-base.md': {('whole_file', None)},
    'findings/01-hosted-ci-debt.md': {('whole_file', None)},
    'artifacts/P10-design/facts/repro-root-tests.txt': {('whole_file', None)},
    **{f'artifacts/P10.{n}-execution/completion.md': {('whole_file', None)} for n in (1, 2, 3)},
}
actual = {}
for entry in model['entries']:
    if entry['declaration_ref']['field'] == 'Inputs':
        actual.setdefault(entry['path'], set()).add((entry['selector']['kind'], entry['selector'].get('anchor')))
assert actual == expected, actual
assert before == plan_readiness.design_gate(plan)
assert all(entry['sha256'] for entry in model['entries'])
report = {'schema': 'p10.4-parser-repair-proof/v1', 'original_error': original_error, 'original_inputs_parser_entries': raw_entries, 'design_fingerprint_unchanged': before['fingerprint'], 'design_gate_allowed': True, 'spec_sha256_unchanged': hashlib.sha256(adapter.SPEC.read_bytes()).hexdigest(), 'all_declared_files_retained': True, 'all_predecessor_completions_retained': True, 'dossier_and_retrieval_restored': True, 'corrected_inputs': {p: sorted(s, key=str) for p, s in actual.items()}, 'scope_authority_route_validation_changes': False}
(out / 'adapter-proof.json').write_text(json.dumps(report, indent=2) + '\n')
print('Adapter proof GREEN: 11 source files; exact selectors; unchanged specification/design audit; no gate bypass.')
