#!/usr/bin/env python3
"""Union PHPUnit XML covered lines while conserving every file's denominator."""
from pathlib import Path
import sys
import xml.etree.ElementTree as ET

NS = '{https://schema.phpunit.de/coverage/1.0}'
floor = int(Path(__file__).with_name('coverage-min-floor.txt').read_text().splitlines()[0])
if len(sys.argv) != 5 or not sys.argv[1].isdigit() or int(sys.argv[1]) < floor:
    sys.exit('coverage: require all three reports and the configured floor')
minimum = int(sys.argv[1])
merged = {}
expected_inventory = None
denominators = {}
for directory in map(Path, sys.argv[2:]):
    index = ET.parse(directory / 'index.xml').getroot()
    project = index.find(NS + 'project')
    if project is None:
        sys.exit('coverage: missing project totals')
    totals = project.find(NS + 'directory/' + NS + 'totals/' + NS + 'lines')
    if totals is None:
        sys.exit('coverage: missing executable line total')
    inventory = {}
    lane_executed = 0
    for path in directory.rglob('*.xml'):
        file = ET.parse(path).getroot().find(NS + 'file')
        if file is None:
            continue
        lines = file.find(NS + 'totals/' + NS + 'lines')
        if lines is None:
            sys.exit(f'coverage: missing file totals: {path}')
        name = file.attrib['path'] + '/' + file.attrib['name']
        executable = int(lines.attrib['executable'])
        covered = {int(line.attrib['nr']) for line in file.findall(NS + 'coverage/' + NS + 'line')}
        if len(covered) != int(lines.attrib['executed']) or len(covered) > executable:
            sys.exit(f'coverage: inconsistent line accounting: {name}')
        if name in inventory:
            sys.exit(f'coverage: duplicate file: {name}')
        inventory[name] = (file.attrib['hash'], executable)
        denominators[name] = max(denominators.get(name, 0), executable)
        lane_executed += len(covered)
        merged.setdefault(name, set()).update(covered)
    if (sum(value[1] for value in inventory.values()) != int(totals.attrib['executable'])
            or lane_executed != int(totals.attrib['executed'])):
        sys.exit('coverage: incomplete report inventory')
    if expected_inventory is None:
        expected_inventory = inventory
    elif {name: data[0] for name, data in inventory.items()} != {name: data[0] for name, data in expected_inventory.items()}:
        changed = sorted(name for name in set(inventory) | set(expected_inventory)
                         if inventory.get(name, (None,))[0] != expected_inventory.get(name, (None,))[0])
        sys.exit('coverage: source files or hashes differ between lanes: ' + ', '.join(changed[:5]))
# Unloaded-file static analysis and Xdebug may differ. Keep the larger per-file
# denominator, including every unexecuted line; this is conservative versus native merge.
executable = sum(denominators.values())
covered = sum(len(lines) for lines in merged.values())
if executable == 0 or any(len(merged[name]) > count for name, count in denominators.items()):
    sys.exit('coverage: invalid merged denominator')
percentage = 100 * covered / executable
print(f'Conservative merged root line coverage: {percentage:.1f}% ({covered}/{executable}), minimum {minimum}%')
sys.exit(0 if percentage >= minimum else 1)
