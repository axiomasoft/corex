#!/usr/bin/env python3
"""Rekey measured P02.3 ceilings without increasing any successor group."""
import json,re,subprocess
from pathlib import Path

old=json.loads(subprocess.check_output(['git','show','HEAD:scripts/quality-ceilings.json'],text=True))
succ=json.loads(Path('scripts/ratchet-successors.json').read_text())
actual={'phpstan':{'root':0},'dependencies':{}}
for p in [Path('phpstan-baseline.neon'),*Path('packages').glob('*/phpstan-baseline.neon'),*Path('products').glob('*/phpstan-baseline.neon')]:
    key='root' if p.parent==Path('.') else p.parent.name
    actual['phpstan'][key]=sum(map(int,re.findall(r'^\s+count: (\d+)$',p.read_text(),re.M)))
for p in [*Path('packages').glob('*/composer.json'),*Path('products').glob('*/composer.json')]:
    f=p.parent/'composer-dependency-analyser.php'
    actual['dependencies'][p.parent.name]=f.read_text().count('// ratchet:') if f.exists() else 0
for kind,values in actual.items():
    limits = dict(old[kind])
    projection = succ.get('source_transfers', {})
    if all(k in limits for k in projection.get('when_before_contains', ['__absent__'])):
        for transfer in projection['entries']:
            amount = transfer.get(kind, 0)
            limits[transfer['from']] -= amount
            limits[transfer['to']] = limits.get(transfer['to'], 0) + amount
    handled=set()
    for g in succ['groups']:
        limit=sum(limits.get(k,0) for k in g['before'])
        value=sum(values.get(k,0) for k in g['after'])
        if value>limit:
            raise SystemExit(f'{kind} group {g}: {value} > {limit}')
        handled.update(g['after'])
    for key,value in values.items():
        if key not in handled and value>limits.get(key,0):
            raise SystemExit(f'{kind}.{key}: {value} > {limits.get(key,0)}')
    if sum(values.values())>sum(old[kind].values()):
        raise SystemExit(f'{kind} total grew')
    print(f'{kind}: {sum(old[kind].values())} -> {sum(values.values())}')
Path('scripts/quality-ceilings.json').write_text(json.dumps({'schema':old['schema'],**{k:dict(sorted(v.items())) for k,v in actual.items()}},indent=4)+'\n')
print('ratchet projection: all successor groups shrank or stayed equal')
