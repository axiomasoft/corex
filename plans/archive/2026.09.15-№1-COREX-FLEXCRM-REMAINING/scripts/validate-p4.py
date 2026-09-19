#!/usr/bin/env python3
"""Read-only P4 structure/hash checker. Executed conformance and acceptance stay item-owned."""
from __future__ import annotations
import argparse
import importlib.util
import json
from pathlib import Path
import re
import sys

loader = importlib.util.spec_from_file_location('validate_p1', Path(__file__).with_name('validate-p1.py'))
if loader is None or loader.loader is None:
    raise ImportError('plan-local validate-p1.py unavailable')
v = importlib.util.module_from_spec(loader)
loader.loader.exec_module(v)
FIELDS = ('Intent', 'Why', 'Scope Included', 'Scope Excluded', 'Inputs', 'Files', 'Required Reads', 'Implementation Rules', 'Code Guidance', 'Validation', 'Deliverables')
REPORTS = {
    'findings/P4-panels.md': ({'R14'}, 'PAN', 7),
    'findings/P4-media-lifecycle.md': ({'R15'}, 'MED', 8),
    'findings/P4-media-conversions.md': ({'R15'}, 'CON', 6),
    'findings/P4-notification-outbox.md': ({'R16'}, 'NTF', 7),
    'findings/P4-notification-channels.md': ({'R16'}, 'CHN', 7),
    'findings/P4-notification-digest.md': ({'R16'}, 'DIG', 7),
    'findings/P4-saved-views.md': ({'R28'}, 'VIEW', 9),
}
R_IDS = {'R14', 'R15', 'R16', 'R28'}
CASE_FIELDS = ('Case', 'R/AC', 'Adapter/profile', 'Given/fault', 'Expected', 'Actual evidence', 'Result', 'Limit/owner/resume')
ADOPTION_FIELDS = ('Surface', 'Proposal hash', 'Protected receipt', 'Supported local adapter', 'Installed contract', 'External owner/resume')


def file(plan, path):
    target = (plan / path).resolve()
    v.require(target.is_relative_to(plan.resolve()) and target.is_file(), f'missing or unsafe plan input: {path}')
    return target


def table(plan, path, heading, columns):
    rows = v.table(file(plan, path), heading, columns)
    v.require(rows, f'{path}: empty {heading}')
    for row in rows:
        v.filled(row, *columns)
    return rows


def evidence(plan, path):
    result = json.loads(file(plan, path).read_text())
    v.require(result.get('isError') is not True, 'retrieval error')
    body = ' '.join(x.get('text', '') for x in result.get('content', []) if isinstance(x, dict))
    v.require(len(body) > 200 and 'No answer found' not in body and 'login required' not in body.lower(), 'empty/failed retrieval')


def design(plan):
    p4 = file(plan, 'phases/P4/P4.md').read_text()
    v.require('СКЕЛЕТ — не детализирован' not in p4 and 'Phase Assurance | v1' in p4, 'skeleton/assurance gap')
    specs = {x.name for x in (plan / 'phases/P4').glob('P4.*.md') if re.fullmatch(r'P4\.\d+\.md', x.name)}
    v.require(specs == {f'P4.{n}.md' for n in range(1, 9)}, 'eight exact specs required')
    for name in sorted(specs):
        body = file(plan, 'phases/P4/' + name).read_text()
        v.require(tuple(re.findall(r'^\*\*([^*]+):\*\*', body, re.M)) == FIELDS, f'{name}: ordered eleven stable fields')
        for field in FIELDS:
            found = re.search(r'\*\*' + re.escape(field) + r':\*\*\s*(.*?)(?=\n\*\*|\Z)', body, re.S)
            v.require(found is not None and found[1].strip() not in {'', '—'}, f'{name}/{field} empty')
        v.require('decisions/D6.md' in body and '--check design' in body, 'D4/D6 missing')
        v.require('[NEEDS CLARIFICATION' not in body, 'unresolved internal choice')
        reads = body.split('**Required Reads:**')[1].split('**Implementation Rules:**')[0]
        for path in re.findall(r'(?:packages|docs|tests|delivery)/[\w./-]+', reads):
            v.require((plan.parents[1] / path.rstrip('.')).is_file(), f'missing committed source read: {path}')
        for path in re.findall(r'findings/P1-[\w-]+\.md', body):
            v.require(path in {'findings/P1-requirements-lock.md', 'findings/P1-acceptance-map.md'}, 'undeclared P1 producer')
    rag = file(plan, 'research/P4-design-rag.md').read_text()
    v.require('**Perplexity:** successful' in rag, 'mandatory RAG pending')
    response = re.search(r'\*\*Search artifact:\*\*\s*`([^`]+)`', rag)
    checksum = re.search(r'\*\*SHA256:\*\*\s*`([0-9a-f]{64})`', rag)
    v.require(response is not None and checksum is not None, 'RAG carrier missing')
    v.require(v.digest(file(plan, response[1])) == checksum[1], 'RAG hash stale')
    evidence(plan, response[1])
    focused = 'artifacts/P4-design/perplexity-response.focused.json'
    evidence(plan, focused)
    v.require(v.digest(file(plan, focused)) in rag, 'focused evidence hash stale')
    for name in ('perplexity-query.initial.txt', 'perplexity-query.txt', 'perplexity-query.focused.txt'):
        query = file(plan, 'artifacts/P4-design/' + name).read_text()
        v.require(not re.search(r'core\s*x|flex\s*crm|R\d{2}|DI-\d|AP-\d|packages/', query, re.I), 'internal context in external query')
    sources = table(plan, 'research/P4-design-rag.md', 'Primary sources', ('Origin', 'URL', 'Supported claim'))
    v.require(len({x['Origin'] for x in sources}) >= 2 and all(x['URL'].startswith('https://') for x in sources), 'primary sources absent')
    choices = table(plan, 'research/P4-design-rag.md', 'Choice / independent origin mapping', ('Choice', 'Independent origins', 'What remains local inference'))
    v.require(len(choices) == 5 and all(len({x.strip() for x in row['Independent origins'].split('+')}) >= 2 for row in choices), 'choice origins missing')
    v.require('## Alternatives' in rag and '## Cross-phase impact' in rag, 'alternatives/impact absent')
    rows = table(plan, 'research/P4-extension-design.md', 'R / AC ownership', ('R', 'Producer', 'Cases / obligations', 'Consumer / gate'))
    v.exact(rows, 'R', R_IDS)
    inventory = table(plan, 'research/P4-extension-design.md', 'Required local case IDs', ('Report', 'Required Case IDs'))
    v.exact(inventory, 'Report', set(REPORTS))
    expected = set()
    for row in inventory:
        _, prefix, count = REPORTS[row['Report']]
        ids = [x.strip() for x in row['Required Case IDs'].split(';')]
        want = {f'{prefix}-{n:02}' for n in range(1, count + 1)}
        v.require(len(ids) == count and set(ids) == want, 'local case inventory drift')
        expected |= want
    invariants = re.findall(r'^\| ((?:PAN|MED|CON|NTF|CHN|DIG|VIEW)-\d{2}) \|', file(plan, 'research/P4-extension-design.md').read_text(), re.M)
    v.require(len(invariants) == 51 and set(invariants) == expected, '51 unique invariant rows required')
    candidate = file(plan, 'research/P4-extension-design.md').read_text()
    for seam in ('ModulePanelCatalogue', 'SqlMediaLibrary', 'ConversionDriver', 'NotificationChannelDriver', 'DeliveryOutcome', 'SettingsPreferenceRepository', 'DigestWakeupHandler', 'SavedViewRepository', 'ViewSchemaProvider', 'default_scope_key', 'sys_saved_view_scopes', 'alreadyDispatched'):
        v.require(seam in candidate, f'frozen seam missing: {seam}')
    v.require('P5' in file(plan, 'phases/P4/P4.6.md').read_text() and re.search(r'full\s+finish', candidate), 'P5 binding/finish obligation missing')
    input_rows = table(plan, 'research/P4-extension-design.md', 'Seal inputs', ('Path', 'Role'))
    v.exact(input_rows, 'Path', set(REPORTS) | {'findings/P1-requirements-lock.md', 'findings/P1-acceptance-map.md', 'findings/P2-contract-catalogue.md', 'findings/P3-storage-context.md', 'research/P4-extension-design.md', 'research/P4-design-rag.md', 'research/03-gates.md', 'research/05-integrated-design.md', 'decisions/D9-headless-extensions.md', 'phases/P4/P4.md'} | {f'phases/P4/P4.{n}.md' for n in range(1, 9)})


def resolve_input(plan, path):
    root = plan.parents[1].resolve()
    own = (plan / path).resolve()
    target = own if own.is_file() else (root / path).resolve()
    v.require(target.is_relative_to(root) and target.is_file(), 'input outside repository or unavailable')
    return target


def branches(plan, path):
    v.require(path in REPORTS, 'unknown P4 branch report')
    v.require('p4-extension-receipts/1' in file(plan, path).read_text(), 'branch version missing')
    rows = table(plan, path, 'Cases', CASE_FIELDS)
    v.exact(rows, 'Case', {r['Case'] for r in rows})
    r_ids, prefix, count = REPORTS[path]
    by_case = {r['Case']: r for r in rows}
    required = {f'{prefix}-{n:02}' for n in range(1, count + 1)}
    v.require(required <= set(by_case) and all(by_case[x]['Result'] == 'PASS' for x in required), 'required local case missing/not PASS')
    seen = set()
    for row in rows:
        linked = set(re.findall(r'R\d{2}', row['R/AC']))
        v.require(linked and linked <= R_IDS, 'case R link invalid')
        seen |= linked
        v.require(row['Result'] in {'PASS', 'FAIL', 'UNSUPPORTED', 'PENDING'}, 'case result invalid')
        if row['Result'] == 'PASS':
            v.require(re.search(r'[0-9a-f]{64}', row['Actual evidence']), 'PASS requires actual proof digest')
    v.require(r_ids <= seen, 'required R coverage missing')
    inputs = table(plan, path, 'Inputs', ('Path', 'SHA256', 'Role'))
    v.exact(inputs, 'Path', {x['Path'] for x in inputs})
    for row in inputs:
        v.require(v.digest(resolve_input(plan, row['Path'])) == row['SHA256'], 'branch input changed')
    table(plan, path, 'Adoption', ADOPTION_FIELDS)


def seal(plan):
    for report in REPORTS:
        branches(plan, report)
    path = 'findings/P4-extension-receipts.md'
    v.require('p4-extension-receipts/1' in file(plan, path).read_text(), 'seal version missing')
    rows = table(plan, path, 'Requirements', ('R', 'Items', 'Local case receipts', 'Installed receipts', 'Pending effective gates', 'Owner', 'Resume'))
    v.exact(rows, 'R', R_IDS)
    # Result labels cannot prove execution: item's immutable Validation evidence remains required.
    edges = table(plan, path, 'Producer edges', ('Producer', 'Contract/version', 'Consumer', 'Required receipt', 'Failure model'))
    v.require({'P6', 'P7', 'P8', 'P9', 'P10', 'P11'} <= {x['Consumer'] for x in edges}, 'consumer edge lost')
    required = {x['Path'] for x in table(plan, 'research/P4-extension-design.md', 'Seal inputs', ('Path', 'Role'))}
    anchors = table(plan, path, 'Sealed inputs', ('Path', 'SHA256'))
    v.exact(anchors, 'Path', {x['Path'] for x in anchors})
    v.require(required <= {x['Path'] for x in anchors}, 'seal required input missing')
    for row in anchors:
        v.require(v.digest(resolve_input(plan, row['Path'])) == row['SHA256'], 'stale P4 seal')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--stage', choices=('design', 'branches', 'seal'), required=True)
    parser.add_argument('--report')
    parser.add_argument('--plan-dir', type=Path, default=Path(__file__).resolve().parent.parent)
    args = parser.parse_args()
    try:
        design(args.plan_dir)
        if args.stage == 'branches':
            branches(args.plan_dir, args.report)
        if args.stage == 'seal':
            seal(args.plan_dir)
        print(json.dumps({'stage': args.stage, 'result': 'PASS', 'specs': 8, 'requirements': 4, 'case_contracts': 51, 'semantic_acceptance': 'item-owned; structural checker never substitutes executed proof', 'runtime_or_live_proof': False}))
        return 0
    except (OSError, ValueError, KeyError, TypeError) as exc:
        print(f'P4 validation FAIL: {exc}', file=sys.stderr)
        return 1


if __name__ == '__main__':
    raise SystemExit(main())
