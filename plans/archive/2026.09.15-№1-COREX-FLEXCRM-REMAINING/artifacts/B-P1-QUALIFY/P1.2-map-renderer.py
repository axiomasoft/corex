"""Render the reviewed classification without altering frozen source criteria."""
from pathlib import Path
import hashlib
import json
import re

plan = Path(__file__).resolve().parents[2]
artifacts = plan / 'artifacts/B-P1-QUALIFY'
model = json.loads((artifacts / 'P1.2-semantic-model.json').read_text())
parents = model['parents']
atoms = [atom for parent in parents for atom in parent['atoms']]
trace = {}
for line in (plan / 'research/01-traceability.md').read_text().splitlines():
    if re.match(r'^\| R\d\d \|', line):
        cells = [cell.strip() for cell in line.split('|')[1:-1]]
        trace[cells[0]] = cells

# These programme anchors retain the portable portion even where the full
# numeric AC is owned by the product or native engine. They are not synthetic AC.
portable = {
    'R01': [('b33', '3.9')], 'R02': [('b33', '2.4'), ('b33', '3.2')],
    'R03': [('b34', '8.4')], 'R04': [('b34', '4.2')],
    'R05': [('b34', '3.3'), ('b34', '4.1')], 'R06': [('b34', '3.2'), ('b34', '4.1')],
    'R07': [('b34', '3.4'), ('b34', '4.1')], 'R08': [('b33', '7'), ('b34', '7')],
    'R09': [('b12', '3.7')], 'R10': [('b12', '3.5'), ('b12', '3.7')],
    'R11': [('b15', '3.3'), ('b15', '2.1')],
    'R12': [('b18', '3.1'), ('b18', '3.2')],
    'R14': [('b16', '3.1')], 'R15': [('b16', '3.5')],
    'R16': [('b16', '3.6')], 'R17': [('b13', '3.4'), ('b38', '2.1')],
    'R20': [('b29', '4.2'), ('b38', '2.3')],
    'R23': [('b35', '3.1'), ('b35', '3.2')],
    'R24': [('b35', '3.7'), ('b35', '3.8')],
    'R25': [('b35', '3.5'), ('b35', '3.6')],
    'R28': [('b31', '2.2')], 'R32': [('b38', '2.1'), ('b15', '3.3')],
    'R33': [('b26', '4'), ('b26', '5'), ('b26', '6'), ('b26', '7')],
    'R36': [('b32', '3.3'), ('b38', '2.1')],
    'R50': [('b38', '6')], 'R51': [('b38', '2.3'), ('b38', '3.3')],
    'R52': [('b38', '4.1'), ('b38', '7')],
}

def section(source, anchor):
    path = plan / f'brief/sources/{source}.md'
    text = path.read_text()
    headings = list(re.finditer(r'^(#{1,6})\s+([^\n]+)', text, re.M))
    found = [i for i, heading in enumerate(headings)
             if re.match(re.escape(anchor) + r'(?:[.\s—–-]|$)', heading[2])
             and not re.match(re.escape(anchor) + r'\.\d', heading[2])]
    assert len(found) == 1, (source, anchor, found)
    index = found[0]
    heading = headings[index]
    stop = next((other.start() for other in headings[index + 1:]
                 if len(other[1]) <= len(heading[1])), len(text))
    body = text[heading.start():stop]
    return f'brief/sources/{source}.md §{anchor}; source SHA256 {hashlib.sha256(path.read_bytes()).hexdigest()}; section SHA256 {hashlib.sha256(body.encode()).hexdigest()}'

def cell(value):
    return str(value).replace('|', r'\|').replace('\n', ' ')

def row(values):
    return '| ' + ' | '.join(cell(value) for value in values) + ' |\n'

def table(columns, rows, chapter, size):
    result = ''
    for start in range(0, len(rows), size):
        result += f'\n### {chapter}.{start // size + 1} Source rows {start + 1}–{min(start + size, len(rows))}\n\n'
        if start == 0:
            result += row(columns) + row([':--'] * len(columns))
        result += ''.join(row(values) for values in rows[start:start + size])
    return result

status = 'qualified acceptance definition' if model['status'] == 'REVIEWED' else 'OPEN semantic review'
text = f'''# P1 acceptance map

## 0. Qualification policy

Status: {status}. All365 historical parents, Original R and text hashes are preserved. Classification defines required acceptance; it reports no executed runtime, API, schema, provider, release or live acceptance.

Conditions retain source clauses and shared parent context. Required evidence names the pinned source, original parent hash and required downstream proof. Full B36 multiline spans and B34 AC-19 incidental invitation reference have separate span hashes. Original B16/B18 numbered labels remain literal. Numeric limits are source-profile requirements, never measured P1 outcomes.

Historical package/class/command/table/UUID names remain source vocabulary. They do not create accepted CoreX contracts or schema. Complete native engines, installed providers, concrete UI/domain/auth/quota/ops behavior retain their own producers. The generic adapter portion has a separate exact non-AC source binding below; moving the full product AC outside CoreX never deletes its already-known reusable requirement. P2 must qualify existing ports before selecting any bounded delta.

Each semicolon clause and explicit mixed outcome is independently owned. Commas inside one transaction, tuple, schema profile, safety invariant or quantified predicate retain their shared constraint. B16.§8.20 projects the unchanged level8/no-ignore predicate onto each of its five exact contracts. B11 AC-8 additionally expands all six referenced post-restore outcomes; B12 AC-10 expands the three approval-mode definitions including impossible quorum. Referencing parent clauses remain covered.

Source-owner contradictions are visible in Correction and flow to P1.4 conflicts. Qualification of a definition does not approve either unresolved source branch. The stricter denial branch of B32 confirmation remains unaccepted pending owner choice; external MCP two-phase and irreversible confirmation are not relaxed.

## Parents
'''
text += table(['AC', 'Original R', 'Text SHA256', 'Atoms', 'Correction'],
              [[parent['label'], parent['original_r'], parent['original_sha256'], len(parent['atoms']), parent['correction']] for parent in parents], 1, 45)
text += '\n## Atoms\n'
columns = ['Atom', 'Parent AC', 'R', 'Boundary', 'Owner', 'Condition', 'Required evidence', 'Destination']
text += table(columns, [[atom[key] for key in columns] for atom in atoms], 2, 18)
text += '\n## Requirements\n'
coverage = []
bindings = {}
for requirement, source in trace.items():
    ids = [atom['Atom'] for atom in atoms if atom['R'] == requirement]
    anchors = [section(*pair) for pair in portable.get(requirement, ())]
    if requirement == 'R49':
        path = plan / 'brief/sources/flex-p6.md'
        body = ''.join(path.read_text().splitlines(keepends=True)[37:40])
        anchors = [f'brief/sources/flex-p6.md:L38–40 deferred quartet; source SHA256 {hashlib.sha256(path.read_bytes()).hexdigest()}; span SHA256 {hashlib.sha256(body.encode()).hexdigest()}']
    if not ids and not anchors:
        # The exact literal FLEX-2 item is an independent programme carrier.
        match = re.search(r'FLEX-2 (P\d+\.\d+)', source[1])
        assert match, (requirement, source[1])
        item = match[1]
        name = 'flex-p91' if item == 'P9.1' else 'flex-p117' if item == 'P11.7' else 'flex-p6'
        path = plan / f'brief/sources/{name}.md'
        assert item in path.read_text(), item
        anchors = [f'brief/sources/{name}.md {item}; source SHA256 {hashlib.sha256(path.read_bytes()).hexdigest()}']
    content = ', '.join(ids)
    if anchors:
        content += ('; ' if content else '') + 'non-AC reusable/programme binding: ' + ' · '.join(anchors)
    bindings[requirement] = anchors
    coverage.append([requirement, content, source[3], source[7]])
text += table(['R', 'Atoms or non-AC source', 'Owner', 'Destination'], coverage, 3, 13)
(plan / 'findings/P1-acceptance-map.md').write_text(text)
(artifacts / 'P1.2-non-ac-bindings.json').write_text(json.dumps(bindings, ensure_ascii=False, indent=2) + '\n')
print(json.dumps({'parents': len(parents), 'atoms': len(atoms), 'status': status, 'bytes': len(text.encode())}))
