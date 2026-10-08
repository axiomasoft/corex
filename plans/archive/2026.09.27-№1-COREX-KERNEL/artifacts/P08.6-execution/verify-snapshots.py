#!/usr/bin/env python3
"""Prove only D113 name, integer-width/default-cast and morph-width changes."""
from pathlib import Path
import json,re
root=Path(__file__).resolve().parents[1]
checked=[]
for item in [6,7,8]:
 for before in sorted((root/f'P08.{item}-execution').glob('*-before*.json')):
  if 'objects' in before.name:continue
  after=before.with_name(before.name.replace('-before','-after'))
  if not after.exists():raise SystemExit('Missing after: '+str(after))
  a=json.loads(before.read_text());b=json.loads(after.read_text());assert a['driver']==b['driver'];assert set(a['tables'])==set(b['tables']),(before,'tables')
  for table,old in a['tables'].items():
   new=b['tables'][table];assert set(old['columns'])==set(new['columns']),(before,table,'columns');wide=set();morph=set()
   for col,d in old['columns'].items():
    e=new['columns'][col];assert d['nullable']==e['nullable'],(before,table,col,'nullable')
    if d['type']!=e['type']:
     assert (d['type'] in ['integer','smallint'] and e['type']=='bigint') or (col=='entity_id' and d['type']=='uuid' and e['type']=='character varying(64)'),(before,table,col,d,e)
     (wide if e['type']=='bigint' else morph).add(col)
    if d['default']!=e['default']:
     assert col in wide and d['default']=='0' and e['default']=="'0'::bigint",(before,table,col,'default',d,e)
   def signature(o,kind):
    if kind=='indexes':return (o['kind'],tuple(o['columns']),o['predicate'],o['method'])
    if kind=='foreign_keys':return (tuple(o['columns']),o['references'],o['on_delete'],o['on_update'],o['deferrable'],o['initially_deferred'])
    return (o['expression'],)
   for kind in ['indexes','foreign_keys','checks']:
    left=sorted(signature(o,kind) for o in old[kind]);right=sorted(signature(o,kind) for o in new[kind]);assert left==right,(before,table,kind)
   # Trigger bodies may change only the names already covered by the corresponding PG constraint rename.
   if a['driver']=='sqlite' and old['triggers']!=new['triggers']:
    pgold=before.with_name(before.name.replace('.sqlite',''));pgnew=after.with_name(after.name.replace('.sqlite',''))
    oa=json.loads(pgold.read_text())['tables'][table];nb=json.loads(pgnew.read_text())['tables'][table];renames={}
    for kind in ['indexes','foreign_keys','checks']:
     for original in oa[kind]:
      matches=[x for x in nb[kind] if signature(x,kind)==signature(original,kind)]
      if len(matches)==1:renames[original['name']]=matches[0]['name']
    def normalize(value):
     for source,target in sorted(renames.items(),key=lambda x:len(x[0]),reverse=True):value=value.replace(source,target)
     return value
    normalized={normalize(k):normalize(v) for k,v in old['triggers'].items()};assert normalized==new['triggers'],(before,table,'triggers')
  checked.append({'item':f'P08.{item}','before':str(before.relative_to(root.parent.parent)),'tables':len(a['tables']),'driver':a['driver']})
print(json.dumps({'status':'GREEN','snapshots':len(checked),'checks':checked},indent=2))
