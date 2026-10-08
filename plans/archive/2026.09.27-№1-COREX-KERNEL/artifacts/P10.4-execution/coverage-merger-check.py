"""Behavioral controls: union overlapping lanes, preserve denominator, reject drift."""
import subprocess
import tempfile
from pathlib import Path
import xml.etree.ElementTree as ET

namespace = 'https://schema.phpunit.de/coverage/1.0'
ET.register_namespace('', namespace)
def node(tag, **attributes):
    return ET.Element('{'+namespace+'}'+tag, {k: str(v) for k, v in attributes.items()})
def report(directory, covered, executable=20):
    directory.mkdir(exist_ok=True)
    root = node('phpunit')
    file = node('file', name='fixture.php', path='/src', hash='fixture-source')
    root.append(file)
    totals = node('totals'); file.append(totals)
    totals.append(node('lines', executable=executable, executed=len(covered)))
    coverage = node('coverage'); file.append(coverage)
    for line in covered:
        coverage.append(node('line', nr=line))
    ET.ElementTree(root).write(directory/'fixture.php.xml')
    root = node('phpunit'); project = node('project', source='/src'); root.append(project)
    folder = node('directory', name='/'); project.append(folder)
    totals = node('totals'); folder.append(totals)
    totals.append(node('lines', executable=executable, executed=len(covered)))
    ET.ElementTree(root).write(directory/'index.xml')
with tempfile.TemporaryDirectory(prefix='corex-p104-coverage-') as temp:
    lanes = [Path(temp)/str(i) for i in range(3)]
    for lane, lines in zip(lanes, [range(1, 10), range(5, 15), range(10, 18)]):
        report(lane, list(lines))
    command = ['python3', 'scripts/merge-root-coverage.py', '80', *map(str, lanes)]
    result = subprocess.run(command, text=True, capture_output=True)
    assert result.returncode == 0 and '85.0% (17/20)' in result.stdout, result
    print('overlap union and unchanged denominator: 17/20, pass')
    report(lanes[2], list(range(5, 10)))
    assert subprocess.run(command, capture_output=True).returncode != 0
    print('below-floor coverage: rejected')
    report(lanes[2], list(range(10, 18)), executable=21)
    result = subprocess.run(command, text=True, capture_output=True)
    assert result.returncode == 0 and '(17/21)' in result.stdout, result
    print('larger static denominator retained: 17/21, pass')
    changed = lanes[2]/'fixture.php.xml'
    changed.write_text(changed.read_text().replace('fixture-source', 'different-source'))
    assert subprocess.run(command, capture_output=True).returncode != 0
    print('source hash drift: rejected')
    command[2] = '79'
    assert subprocess.run(command, capture_output=True).returncode != 0
    print('weakened floor: rejected')
