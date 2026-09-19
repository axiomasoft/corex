"""Verify source coverage independently of the renderer; semantic judgement is separate."""
from pathlib import Path
import hashlib
import importlib.util
import json
import re

plan = Path(__file__).resolve().parents[2]
artifacts = plan / 'artifacts/B-P1-QUALIFY'
module_spec = importlib.util.spec_from_file_location('validator', plan / 'scripts/validate-p1.py')
validator = importlib.util.module_from_spec(module_spec)
module_spec.loader.exec_module(validator)
_, criteria, requirements = validator.inputs(plan)
model = json.loads((artifacts / 'P1.2-semantic-model.json').read_text())
parents = model['parents']
assert len(parents) == len(criteria) == 365
assert {parent['label'] for parent in parents} == set(criteria)
checks = []
for parent in parents:
    label = parent['label']
    original = criteria[label]
    assert parent['original_sha256'] == original['sha256']
    assert hashlib.sha256(parent['original_text'].encode()).hexdigest() == original['sha256']
    assert set(re.findall(r'R\d{2}', parent['original_r'])) == original['r_ids']
    if parent['full_span'] is None:
        assert parent['full_body'] == parent['original_text']
    elif label == 'B34 AC-19':
        body = ''.join((plan / 'brief/sources/b34.md').read_text().splitlines(keepends=True)[204:212])
        assert body == parent['full_span']
        assert re.sub(r'\s+', ' ', body).strip() == parent['full_body']
    else:
        number = int(label.split('-')[-1])
        source = (plan / 'brief/sources/b36.md').read_text()
        section = source[source.index('### 8.5 Acceptance criteria'):]
        match = re.search(r'^- AC-' + str(number) + r'\. (.*?)(?=\n- AC-\d+\.|\n---|\Z)', section, re.M | re.S)
        assert match and match[0].rstrip() == parent['full_span']
        assert re.sub(r'\s+', ' ', match[1]).strip() == parent['full_body']
    primary = [atom for atom in parent['atoms'] if 'Referenced target' not in atom]
    assert ''.join(atom['Source fragment'] for atom in primary) == parent['full_body'], label
    if label == 'B16.§8.20':
        assert {atom['Focus'] for atom in primary} == {'PanelHost', 'MessageService', 'PermissionResolver', 'NotificationCenter', 'RelationLookup'}
        assert all('level 8' in atom['Condition'] and '@phpstan-ignore' in atom['Condition'] for atom in primary)
    else:
        # Every numeric source-profile constraint remains literal in its owned condition.
        for atom in primary:
            assert set(re.findall(r'\d+(?:[.,]\d+)*', atom['Source fragment'])) <= set(re.findall(r'\d+(?:[.,]\d+)*', atom['Condition'])), atom['Atom']
    referenced = [atom for atom in parent['atoms'] if 'Referenced target' in atom]
    if label in ('B11 AC-8', 'B12 AC-10'):
        source, first, last = ('b11', 864, 869) if label == 'B11 AC-8' else ('b12', 842, 844)
        lines = (plan / f'brief/sources/{source}.md').read_text().splitlines(keepends=True)
        expected = {f'brief/sources/{source}.md:L{i}' for i in range(first, last + 1)}
        assert {atom['Referenced target'] for atom in referenced} == expected
        for target in expected:
            number = int(target.split(':L')[1])
            assert ''.join(atom['Source fragment'] for atom in referenced if atom['Referenced target'] == target) == lines[number - 1]
    else:
        assert not referenced
    assert all(atom['R'] in requirements and atom['Boundary'] != 'cross-cutting' for atom in parent['atoms'])
    checks.append({'parent': label, 'original_sha256': original['sha256'], 'full_body_sha256': hashlib.sha256(parent['full_body'].encode()).hexdigest(), 'coverage': 'lossless', 'primary_atoms': len(primary), 'referenced_atoms': len(referenced)})
rows = validator.table(plan / 'findings/P1-acceptance-map.md', 'Atoms', ('Atom', 'Parent AC', 'R', 'Boundary', 'Owner', 'Condition', 'Required evidence', 'Destination'))
expected = {atom['Atom']: atom for parent in parents for atom in parent['atoms']}
assert len(rows) == len(expected)
for row in rows:
    atom = expected[row['Atom']]
    for key in row:
        actual = row[key].replace(r'\|', '|')
        wanted = str(atom[key]).replace('\n', ' ').strip().strip('`')
        assert actual == wanted, (row['Atom'], key)
result = {'schema_version': 'p1-lossless-coverage/v1', 'result': 'PASS', 'semantic_review': 'separate root assessment; no automated acceptance', 'parents': checks, 'atoms': len(expected), 'map_sha256': hashlib.sha256((plan / 'findings/P1-acceptance-map.md').read_bytes()).hexdigest()}
(artifacts / 'P1.2-lossless-coverage.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'result': 'PASS', 'parents': len(parents), 'atoms': len(expected), 'cross_cutting_atoms': 0, 'referenced_restore_lines': 6, 'referenced_approval_lines': 3}))
