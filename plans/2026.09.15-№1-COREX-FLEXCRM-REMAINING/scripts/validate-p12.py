#!/usr/bin/env python3
"""Read-only PLAN1.P12 structure/identity checks; actual acceptance remains item-owned."""
from __future__ import annotations
import argparse
import gzip
import hashlib
import importlib.util
import json
from pathlib import Path
import re
import subprocess
import sys

loader = importlib.util.spec_from_file_location('p12_base', Path(__file__).with_name('validate-p4.py'))
if loader is None or loader.loader is None:
    raise ImportError('plan-local base checker missing')
a = importlib.util.module_from_spec(loader)
loader.loader.exec_module(a)
v = a.v

DESIGN = 'research/P12-rebaseline-design.md'
RECONCILIATION = 'research/P12-audit-reconciliation.md'
RAG = 'research/P12-design-rag.md'
DECISION = 'decisions/D41-package-boundary-rebaseline.md'
ITEMS = 9

# Each item names the design paragraph that carries its code, so an executor never invents one.
DESIGN_ANCHORS = {
    1: '§1', 2: '§3', 3: '§2', 4: '§4', 5: '§5', 6: '§6', 7: '§7', 8: '§8', 9: '§10',
}
# Audits are non-normative raw material; these are the verdicts P12 is allowed to rely on.
VERDICT_IDS = {
    'A1', 'A2', 'A2a', 'A2b', 'A3', 'NEW-1',
    'B1', 'B2', 'B3', 'B4', 'B5', 'B7',
    'C1', 'C2', 'C3',
    'TR1', 'TR2', 'TR3', 'TR4', 'TR5',
    'E1', 'E2', 'E3',
    'F1', 'F2', 'F4',
    'G1', 'G2', 'G3',
}
NEUTRALITY = r'core\s*x|flex\s*crm|R\d{2}|DI-\d|AP-\d|packages/'


def design(plan):
    phase = a.file(plan, 'phases/P12/P12.md').read_text()
    v.require('СКЕЛЕТ — не детализирован' not in phase and 'Phase Assurance | v1' in phase, 'phase detail/assurance missing')
    v.require('[OWNER-GATE:G-API]' in phase or 'G-API' in phase, 'protected-change disclosure missing from phase')

    specs = {x.name for x in (plan / 'phases/P12').glob('P12.*.md') if re.fullmatch(r'P12\.\d+\.md', x.name)}
    v.require(specs == {f'P12.{n}.md' for n in range(1, ITEMS + 1)}, f'{ITEMS} exact specs required')

    gated = []
    for n in range(1, ITEMS + 1):
        name = f'P12.{n}.md'
        body = a.file(plan, 'phases/P12/' + name).read_text()
        v.require(tuple(re.findall(r'^\*\*([^*]+):\*\*', body, re.M)) == a.FIELDS, f'{name}: ordered eleven stable fields')
        for field in a.FIELDS:
            found = re.search(r'\*\*' + re.escape(field) + r':\*\*\s*(.*?)(?=\n\*\*|\Z)', body, re.S)
            v.require(found is not None and found[1].strip() not in {'', '—'}, f'{name}/{field} empty')
        v.require('decisions/D6.md' in body and '--check design' in body, f'{name}: D4/D6 missing')
        v.require('[NEEDS CLARIFICATION' not in body, f'{name}: unresolved internal choice')
        v.require(DECISION in body, f'{name}: owning decision not declared')
        v.require(DESIGN in body and RECONCILIATION in body, f'{name}: normative inputs not declared')
        v.require(DESIGN_ANCHORS[n] in body, f'{name}: design paragraph anchor {DESIGN_ANCHORS[n]} missing')

        # Renames land in P12.4/P12.6, so Required Reads must not pin a path this phase moves.
        reads = body.split('**Required Reads:**')[1].split('**Implementation Rules:**')[0]
        for path in re.findall(r'(?:packages|docs|tests|delivery)/[\w./-]+', reads):
            v.require((plan.parents[1] / path.rstrip('.')).is_file(), f'{name}: missing committed source read: {path}')
        for moved in ('packages/import-export/', 'packages/intl-search/'):
            v.require(moved not in reads, f'{name}: Required Reads pins a path this phase renames: {moved}')

        # Frozen DDL and dependency discipline must be restated where an executor can see it.
        v.require('миграц' in body, f'{name}: frozen-migration boundary not restated')
        if '[OWNER-GATE:' in body:
            gated.append(name)

    v.require(gated == ['P12.8.md'], 'exactly one owner-gated item expected (P12.8)')

    # Audits are inputs, never specifications: the design must forbid copying their code.
    design_body = a.file(plan, DESIGN).read_text()
    v.require('audits/' in design_body and 'запрещ' in design_body, 'audit non-normative status not stated in design')
    # A `§N` reference is applied by Bundle v1 as a section selector, so the paragraph must
    # exist as a heading the resolver can actually find (`## N.`), not merely as prose.
    for anchor in set(DESIGN_ANCHORS.values()):
        number = anchor.lstrip('§')
        v.require(re.search(rf'^#+\s+{re.escape(number)}(?:[.\s]|$)', design_body, re.M) is not None,
                  f'design paragraph {anchor} is not a resolvable heading')

    bundles(plan)

    verdicts = a.table(plan, RECONCILIATION, '1. Матрица проверенных утверждений', ('ID', 'Утверждение аудита', 'Verdict', 'Доказательство'))
    v.exact(verdicts, 'ID', VERDICT_IDS)
    refuted = {x['ID'] for x in verdicts if 'ОПРОВЕРГНУТО' in x['Verdict']}
    v.require(refuted == {'A2', 'E2', 'E3', 'G2'}, 'refuted-claim set drift')
    v.require(any(x['ID'] == 'NEW-1' and 'P0' in x['Verdict'] for x in verdicts), 'self-found P0 not carried as P0')

    rag(plan)


# P12.9 seals the phase from the eight preceding receipts, so its declared inputs cannot
# exist before execution starts. Every other item must hand the executor a complete bundle.
FUTURE_INPUTS = {'findings/P12-rebaseline-receipts.md', 'artifacts/P12-package-graph/report.json'}
DEPENDENCY_NOT_READY = {9}


def task_runtime() -> Path:
    """Locate the configured Task runtime the same way scripts/task-check.py does."""
    metadata = subprocess.run(['codex', 'plugin', 'marketplace', 'list', '--json'],
                              capture_output=True, text=True, check=True)
    roots = [Path(row['root']) / 'packages/task'
             for row in json.loads(metadata.stdout).get('marketplaces', [])
             if row.get('name') == 'swissknifeman']
    if len(roots) != 1 or not (roots[0] / 'lib/task').is_dir():
        raise ValueError('expected one configured Swissknifeman Task runtime')
    return roots[0]


def bundles(plan):
    """Prove the executor actually receives its declared inputs, not just a path list."""
    sys.path.insert(0, str(task_runtime() / 'lib'))
    from task import plan_bundle, plan_weight

    repo = plan.parents[1]
    for n in range(1, ITEMS + 1):
        body = a.file(plan, f'phases/P12/P12.{n}.md').read_text()
        for field in ('Inputs', 'Required Reads'):
            found = re.search(rf'\*\*{field}:\*\*(.*?)(?=\n\*\*|\Z)', body, re.S)
            raw = ' '.join(found[1].split())
            entries = plan_weight.split_entries(raw)
            if field == 'Inputs' and len(entries) == 1:
                entries = [x.strip() for x in entries[0].split(' · ') if x.strip()]
            for entry in entries:
                declared = [t for t, is_declared in plan_weight.path_candidates(entry) if is_declared]
                v.require(declared, f'P12.{n}/{field}: entry carries no declared path: {entry[:60]}')
                resolved = []
                for token in declared:
                    target = plan_weight.resolve_candidate(token, (plan, repo))
                    v.require(target is not None or token in FUTURE_INPUTS,
                              f'P12.{n}/{field}: declared path does not exist: {token}')
                    if target is not None:
                        resolved.append(target)

                # Bundle v1 takes the FIRST heading matching `^#+\s+<anchor>[.\s]`, so a
                # heading like `## §1.` silently yields subsection `1.1` instead of failing.
                # Require the selected heading to carry exactly the requested number.
                anchors = re.findall(r'§\s*(\d+(?:\.\d+)*)', entry)
                if anchors and len(resolved) == 1:
                    text = resolved[0].read_text()
                    for anchor in anchors:
                        line = next((x for x in text.splitlines()
                                     if re.match(rf'^#+\s+{re.escape(anchor)}(?:[.\s]|$)', x)), None)
                        v.require(line is not None,
                                  f'P12.{n}/{field}: §{anchor} not found in {resolved[0].name}')
                        selected = re.match(r'^#+\s+(\d+(?:\.\d+)*)', line)[1]
                        v.require(selected == anchor,
                                  f'P12.{n}/{field}: §{anchor} resolves to section {selected} '
                                  f'in {resolved[0].name} — heading is not resolver-exact')

        # Prose cross-references are not bundle selectors, but an executor still follows
        # them, so every `§N` named anywhere in a spec must exist in the design document.
        design_body = a.file(plan, DESIGN).read_text()
        for anchor in set(re.findall(r'§\s*(\d+(?:\.\d+)*)', body)):
            line = next((x for x in design_body.splitlines()
                         if re.match(rf'^#+\s+{re.escape(anchor)}(?:[.\s]|$)', x)), None)
            v.require(line is not None and re.match(r'^#+\s+(\d+(?:\.\d+)*)', line)[1] == anchor,
                      f'P12.{n}: §{anchor} is referenced but has no exact heading in the design')

        if n in DEPENDENCY_NOT_READY:
            continue
        try:
            plan_bundle.render(plan, f'P12.{n}')
        except plan_bundle.BundleError as exc:
            raise ValueError(f'P12.{n}: bundle does not assemble: {exc}') from exc


def rag(plan):
    body = a.file(plan, RAG).read_text()
    v.require('**Perplexity:** successful' in body, 'mandatory RAG pending')
    artifact = re.search(r'\*\*Search artifact:\*\*\s*`([^`]+)`', body)
    checksum = re.search(r'\*\*SHA256:\*\*\s*`([0-9a-f]{64})`', body)
    v.require(artifact is not None and checksum is not None, 'RAG carrier missing')
    v.require(v.digest(a.file(plan, artifact[1])) == checksum[1], 'RAG hash stale')
    a.evidence(plan, artifact[1])
    v.require('## Alternatives' in body and '## Cross-phase impact' in body, 'alternatives/impact absent')
    v.require('## Failed verification' in body, 'failed-verification disclosure absent')

    sources = a.table(plan, RAG, 'Primary sources', ('Origin', 'URL', 'Supported claim'))
    v.require(len({x['Origin'] for x in sources}) >= 2 and all(x['URL'].startswith('https://') for x in sources), 'primary sources absent')
    choices = a.table(plan, RAG, 'Choice / independent origin mapping', ('Choice', 'Independent origins', 'What remains local inference'))
    v.require(all(len({y.strip() for y in x['Independent origins'].split('+')}) >= 2 for x in choices), 'choice origins missing')

    # Every cited primary source must be backed by its own retrieved bytes.
    retrieval = json.loads(a.file(plan, 'artifacts/P12-design/primary-source-retrieval.json').read_text())
    v.require({x['url'] for x in retrieval} == {x['URL'] for x in sources}, 'direct source retrieval gap')
    for row in retrieval:
        raw = gzip.decompress(a.file(plan, row['artifact']).read_bytes())
        v.require(row['http'] == 200 and len(raw) == row['bytes'] and hashlib.sha256(raw).hexdigest() == row['sha256'], 'primary raw bytes invalid')

    history = json.loads(a.file(plan, 'artifacts/P12-design/retrieval-history.json').read_text())
    for row in history['queries']:
        v.require(not re.search(NEUTRALITY, row['query'], re.I), 'internal premise in external query')
        # A timed-out or empty query may be recorded, but never counted as evidence.
        v.require(row['outcome'] == 'successful' or row.get('counted_as_evidence') is False, 'failed query counted as evidence')
    v.require(any(x['outcome'] == 'successful' for x in history['queries']), 'no successful external query recorded')

    query_file = a.file(plan, 'artifacts/P12-design/perplexity-query.txt').read_text()
    v.require(not re.search(NEUTRALITY, query_file, re.I), 'internal context in external query file')

    integrity = json.loads(a.file(plan, 'artifacts/P12-design/integrity.json').read_text())
    for row in integrity['responses']:
        target = a.file(plan, 'artifacts/P12-design/' + row['file'])
        raw = target.read_bytes()
        v.require(len(raw) == row['bytes'] and hashlib.sha256(raw).hexdigest() == row['sha256'], 'response artifact digest stale')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--check', choices=('design',), default='design')
    parser.add_argument('--plan-dir', type=Path, default=Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    try:
        design(args.plan_dir)
        print('P12 structural identity check PASS; actual executed/accepting-owner truth remains item-owned')
        return 0
    except (OSError, ValueError, KeyError, IndexError, json.JSONDecodeError) as exc:
        print('P12 check FAIL: ' + str(exc), file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
