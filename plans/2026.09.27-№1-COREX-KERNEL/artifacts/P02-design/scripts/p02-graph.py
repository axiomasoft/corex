#!/usr/bin/env python3
"""Project graph debt and public metadata through the exact old/new class owners."""
import json,re,subprocess,importlib.util
from pathlib import Path

plan=json.loads(Path('build/p02/plan.json').read_text());classes=plan['all_classes'];sources=plan['class_sources'];moves=dict(plan['moves'])
manifests={p:json.loads(p.read_text()) for p in [*Path('packages').glob('*/composer.json'),*Path('products').glob('*/composer.json')]}
by_name={v['name']:p for p,v in manifests.items()}
def write(p,d):p.write_text(json.dumps(d,indent=4,ensure_ascii=False)+'\n')
def own_file(f):return next((v['name'] for p,v in manifests.items() if f.startswith(str(p.parent)+'/')),None)
def old_family(f):
    folder='/'.join(f.split('/')[:2])
    mapping={r.split('\t')[0]:r.split('\t')[3] for r in Path('plans/2026.09.27-№1-COREX-KERNEL/artifacts/P00/package-map.tsv').read_text().splitlines()[1:] if r}
    return mapping.get(folder)
index={final:own_file(moves.get(sources[old],sources[old])) for old,final in classes.items()}
old_index={final:old_family(sources[old]) for old,final in classes.items()}
reverse_moves={new:old for old,new in moves.items()}

def imports(path):
    p=Path(path)
    if not p.exists():return []
    return re.findall(r'^use\s+(CoreX\\[\w\\]+)(?:\s+as\s+\w+)?\s*;',p.read_text(),re.M)

# Preserve old public namespace scopes after their members move into features.
# The current scalar/class prefixes introduced by the seam items stay intact.
for p,m in manifests.items():
    projected=[]
    for prefix in m['extra']['corex']['public-api']:
        matching=[(old,new) for old,new in classes.items() if old==prefix or old.startswith(prefix+'\\')]
        projected.extend((new if old==prefix else new.rsplit('\\',1)[0]) for old,new in matching if index.get(new)==m['name'])
        if not matching:projected.append(prefix)
    m['extra']['corex']['public-api']=sorted(set(projected))

for name,provider in [('axiomasoft/corex-webhooks','CoreX\\Webhooks\\WebhooksServiceProvider')]:
    p=by_name[name];manifests[p]['extra']['corex']['public-api'].append(provider)

for old, symbol in plan['public_origins'].items():
    if index.get(symbol):
        manifests[by_name[index[symbol]]]['extra']['corex']['public-api'].append(symbol)
for old,symbol in classes.items():
    if '\\Internal\\' in old and '\\Internal\\' not in symbol and index.get(symbol):
        manifests[by_name[index[symbol]]]['extra']['corex']['public-api'].append(symbol)

# A reference that was internal to one physical package before its split is
# now an explicit exported contract. No existing cross-package import is granted.
exported=[]
for p,m in manifests.items():
    for file in p.parent.joinpath('src').rglob('*.php'):
        f=str(file);family=old_family(reverse_moves.get(f,f))
        for symbol in imports(f):
            target=index.get(symbol)
            if target and target!=m['name'] and (old_index.get(symbol)==family or '/tests/' in reverse_moves.get(f,f) and '/src/Testing/' in f):
                dst=manifests[by_name[target]]['extra']['corex']['public-api']
                if not any(symbol==x or symbol.startswith(x+'\\') for x in dst):
                    dst.append(symbol);exported.append({'source':f,'symbol':symbol,'owner':target})
for p,m in manifests.items():
    declared=set(m['extra']['corex']['public-api'])
    m['extra']['corex']['public-api']=sorted(symbol for symbol in declared if not any(symbol.startswith(prefix+'\\') for prefix in declared if prefix!=symbol));write(p,m)
Path('build/p02/exported-split-contracts.json').write_text(json.dumps(exported,indent=2)+'\n')

spec=importlib.util.spec_from_file_location('transfer',Path(__file__).with_name('p02-transfer.py'));transfer=importlib.util.module_from_spec(spec);spec.loader.exec_module(transfer)
def preimage(path):
    raw=subprocess.check_output(['git','show','HEAD:'+path],text=True)
    return json.loads(transfer.rewrite_text(raw,*transfer.literal_rules(plan)))
baseline=preimage('scripts/package-graph-undeclared-surface.json')
entries=[]
for entry in baseline['entries']:
    directory,symbol=entry.split('|',1)
    owner=index.get(symbol)
    if owner:
        entries.append(by_name[owner].parent.name+'|'+symbol)
baseline['entries']=sorted(set(entries));write(Path('scripts/package-graph-undeclared-surface.json'),baseline)

config=preimage('scripts/package-graph-gate.json')
exceptions=[]
for entry in config['public_api_exceptions']:
    source=own_file(entry['import_file'])
    for target in sorted({index[s] for s in imports(entry['import_file']) if s in index and old_index.get(s)==entry['to']}):
        if target and target!=source:
            exceptions.append({**entry,'from':source,'to':target})
config['public_api_exceptions']=list({(e['from'],e['to'],e['import_file']):e for e in exceptions}.values())
# These exact old Internal composition edges have disappeared after same-owner
# moves; keep only edges that still join two owners.
config['internal_composition_edges']=[{**e,'from':own_file(e['import_file']),'to':own_file(e['target_file'])} for e in config['internal_composition_edges'] if own_file(e['import_file'])!=own_file(e['target_file'])]
write(Path('scripts/package-graph-gate.json'),config)
print(f'graph projection: {len(entries)} retained surface debts; {len(exported)} explicit split contracts; {len(exceptions)} projected exception relations')
