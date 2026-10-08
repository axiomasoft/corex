#!/usr/bin/env python3
"""Read-only reproductions of PLAN3 P00 design findings; run from repo root.

Usage: python3 <this-file> --task-root <swissknifeman/packages/task>
This models the written algorithms; check-maps.py does not exist yet.
"""
import argparse
import difflib
import json
from pathlib import Path
import re
import subprocess
import sys
import tempfile

parser = argparse.ArgumentParser()
parser.add_argument('--task-root', type=Path, required=True)
args = parser.parse_args()
sys.path.insert(0, str(args.task_root / 'lib'))
sys.path.insert(0, str(args.task_root.parent.parent))
from task import plan_decisions, plan_readiness, task_contract

root = Path.cwd()
plan = root / 'plans/2026.09.27-№1-COREX-KERNEL'

# A1: both edges are real imports under the proposed F9 directory ownership.
edges = [
    ('operations', 'webhooks', 'packages/operations/src/OpenApi/Internal/ContractSnapshotBuilder.php',
     'use CoreX\\Api\\Data\\WebhookTopic;', 'packages/operations/src/Webhooks/Data/WebhookTopic.php'),
    ('webhooks', 'operations', 'packages/operations/src/Webhooks/Laravel/WebhookRouteRegistrar.php',
     'use CoreX\\Api\\OperationRegistry;', 'packages/operations/src/Definition/OperationRegistry.php'),
]
graph = []
for source, target, path, literal, definition in edges:
    text = (root / path).read_text()
    assert literal in text and (root / definition).is_file()
    graph.append(dict(source=source, target=target, path=path,
                      line=text[:text.index(literal)].count('\n') + 1,
                      import_statement=literal, definition=definition))

# A2: the documented parent row remains after deleting the Tools row.
file = 'packages/operations/src/Tools/ToolServiceProvider.php'
assert (root / file).is_file()
paths = ['packages/operations/src/', 'packages/operations/src/Tools/',
         'packages/operations/src/Tools/Contracts/']
def winner(rows):
    return max((p for p in rows if file.startswith(p)), key=len, default=None)
fallback = dict(file=file, before=winner(paths),
                after=winner([p for p in paths if p != 'packages/operations/src/Tools/']),
                expected_by_example='uncovered')

# A3: a valid complete constitution table plus required policy rows.
ids = set(re.findall(r'^### ([A-Z]+-[0-9]+)',
                     (root / 'docs/architecture/constitution.md').read_text(), re.M))
delta_ids = ids | {'§1', 'NEW-1'}
with tempfile.TemporaryDirectory(prefix='corex-plan3-audit-') as directory:
    sample = Path(directory) / 'architecture.md'
    sample.write_text('Норма с P00.2; все имена окончательные.\n')
    grep = subprocess.run(['grep', '-c', '≈', str(sample)], capture_output=True, text=True)
doctrine = dict(constitution_ids=len(ids), extra_required_ids=sorted(delta_ids - ids),
                literal_set_equality=ids == delta_ids,
                grep_stdout=grep.stdout.strip(), grep_exit=grep.returncode)

# A4: invoke the exact decision loader that used to raise duplicate IDs.
decisions = plan_decisions.load_decisions(root / 'plans/2026.09.19-№1-COREX-PLATFORM-HARDENING')
decision_check = dict(count=len(decisions), duplicate_id_exception=False,
                      expected_by_P00_3='task.plan_decisions.DecisionError: идентификаторы решений повторяются')

script = plan / 'artifacts/P00-design/scripts/zone-consumers.py'
facts = plan / 'artifacts/P00-design/facts/zone-consumers.tsv'
live = subprocess.run([sys.executable, str(script)], capture_output=True, text=True, check=True)
check = subprocess.run([sys.executable, str(script), '--check', str(facts)], capture_output=True, text=True)
facts_delta = ''.join(difflib.unified_diff(facts.read_text().splitlines(True), live.stdout.splitlines(True),
                                         fromfile='frozen facts', tofile='live facts'))
print(json.dumps(dict(
    schema='plan3-design-audit-reproduction/v1',
    commit=subprocess.check_output(['git', 'rev-parse', 'HEAD'], text=True).strip(),
    candidate=plan_readiness.design_snapshot(plan),
    routing=task_contract.compile_batch_routing(plan),
    A1=dict(edges=graph, cycle=['operations', 'webhooks', 'operations']),
    A2=fallback, A3=doctrine, A4=decision_check,
    A5=dict(exit=check.returncode, diff=facts_delta),
), ensure_ascii=False, indent=2))
