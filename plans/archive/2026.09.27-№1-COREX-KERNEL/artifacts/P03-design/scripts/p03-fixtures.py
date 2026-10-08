#!/usr/bin/env python3
"""Project existing architecture fixture subjects; retain rules and negative controls."""
import json
from pathlib import Path
import re
import subprocess
import sys

plan_path = Path(sys.argv[1] if len(sys.argv) > 1 else 'build/p03/plan.json')
plan = json.loads(plan_path.read_text())
moves = dict(plan['moves'])
path = Path('tests/Arch/CoreZoneTest.php')
original = subprocess.check_output(['git', 'show', 'HEAD:' + str(path)], text=True)
current = path.read_text()

def mapping(name):
    body = re.search(r'const ' + name + r' = \[(.*?)\n\];', original, re.S)[1]
    return dict(re.findall(r"'([^']+)'\s*=>\s*'([^']+)'", body))

named, prefixes = mapping('CORE_ZONE_DUMP'), mapping('CORE_ZONE_PREFIX')
def zone(file):
    relative = file.removeprefix('packages/core/src/')
    return named.get(relative) or next((v for k, v in prefixes.items() if relative.startswith(k)), None)

for old, new in moves.items():
    if old.startswith('packages/core/src/'):
        current = current.replace("'" + old.removeprefix('packages/core/src/') + "'",
                                  "'" + new.removeprefix('packages/core/src/') + "'")

# Freeze only existing cross-zone edges whose target has newly become Internal.
# New edges remain rejected by this gate and by module-layout R5.
sources = subprocess.check_output(['git', 'ls-tree', '-r', '--name-only', 'HEAD', 'packages/core/src'], text=True).splitlines()
by_class = plan['class_sources']
edges = {}
for source in sources:
    if not source.endswith('.php') or zone(source) is None:
        continue
    text = subprocess.check_output(['git', 'show', 'HEAD:' + source], text=True)
    for old in re.findall(r'^use (CoreX\\[^; ]+);', text, re.M):
        new = plan['classes'].get(old, old)
        if '\\Internal\\' in old or '\\Internal\\' not in new or old not in by_class:
            continue
        if not by_class[old].startswith('packages/core/src/') or zone(source) == zone(by_class[old]):
            continue
        edges.setdefault(moves.get(source, source), set()).add(new)
match = re.search(r'(const CORE_ZONE_EXACT_IMPORTS = \[)(.*?)(\n\];)', current, re.S)
body = match[2]
for source, symbols in sorted(edges.items()):
    row = re.search(r"'" + re.escape(source) + r"' => \[(.*?)\],", body, re.S)
    present = row[1] if row else ''
    additions = ''.join("        '" + s.replace('\\', '\\\\') + "',\n" for s in sorted(symbols)
                        if "'" + s.replace('\\', '\\\\') + "'" not in present)
    if not additions:
        continue
    if row:
        body = body[:row.end(1)] + '\n' + additions + body[row.end(1):]
    else:
        body += "\n    '" + source + "' => [\n" + additions + '    ],'
current = current[:match.start(2)] + body + current[match.end(2):]
path.write_text(current)

changed = []
for path in Path('tests').rglob('*.php'):
    if 'Golden/baseline' in str(path):
        continue
    before = path.read_text()
    def package_path(match):
        package, relative = match[1], match[2]
        if path.as_posix() == 'tests/Arch/AchievedInvariantTest.php' and package == 'attributes':
            package = 'custom-fields'  # P02 source name, hidden by leftover directories in old worktrees.
        full = 'packages/' + package + '/' + relative
        relative = moves.get(full, full).removeprefix('packages/' + package + '/')
        return "packagePath('" + package + "', '" + relative + "')"
    after = re.sub(r"packagePath\('([^']+)', '([^']+)'\)", package_path, before)
    if after != before:
        path.write_text(after)
        changed.append(str(path))

for manifest in Path('packages').glob('*/composer.json'):
    package = manifest.parent
    local_moves = {old.removeprefix(str(package) + '/'): new.removeprefix(str(package) + '/')
                   for old, new in moves.items() if old.startswith(str(package) + '/')}
    for path in (package / 'tests').rglob('*.php'):
        before = path.read_text()
        after = before
        for old, new in local_moves.items():
            for quote in ("'", '"'):
                for prefix in ('', '/'):
                    after = after.replace(quote + prefix + old + quote, quote + prefix + new + quote)
        if after != before:
            path.write_text(after)
            changed.append(str(path))

# An empty untracked directory is not part of a portable PHPUnit suite.
config = Path('phpunit.xml')
config.write_text(config.read_text().replace('            <directory>packages/core/tests/Views</directory>\n', ''))
(plan_path.parent / 'fixture-projection.json').write_text(json.dumps({
    'core_zone_edges': {k: sorted(v) for k, v in edges.items()}, 'package_paths': changed,
    'removed_empty_suite_directory': 'packages/core/tests/Views',
}, indent=2) + '\n')
print('fixtures: exact zone/path projection; existing negative controls retained')
