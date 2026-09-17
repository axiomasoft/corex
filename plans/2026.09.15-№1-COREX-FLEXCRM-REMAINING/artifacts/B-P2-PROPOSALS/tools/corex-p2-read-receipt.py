from pathlib import Path
from task import plan_context
import pickle,json,sys
p=Path('plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING');i=sys.argv[1];s=pickle.loads(Path('/tmp/corex-p2-context-'+i+'.pkl').read_bytes());s.assert_live_integrity();old=pickle.loads(Path('/tmp/corex-p2-context.pkl').read_bytes());reuse=[]
for role in ('plan-core','phase-context'):
 x=s._member(role);o=old._member(role);assert x.content==o.content;reuse.append({'role':role,'sha256':x.sha256,'source_snapshot':old.snapshot_sha256,'reason':'byte-identical full terminal root read before D20; no summary substitution'})
chunks=[]
for m in s.members:
 if m.role in ('plan-core','phase-context'): chunks.append(s.chunk(m.role,offset=0,limit=len(m.content)))
 elif m.role=='bundle':
  for off in range(0,len(m.content),22000):chunks.append(s.chunk(m.role,offset=off,limit=22000))
 else:chunks.append(s.chunk(m.role,offset=0,limit=20000))
plan_context.validate_chunks(s,chunks);receipt=s.receipt();receipt['root_byte_reuse']=reuse;receipt['exposed_chunks']=[{k:v for k,v in x.items() if k!='content'} for x in chunks];receipt['status']='complete';(p/'artifacts/B-P2-PROPOSALS'/f'{i}-context.json').write_text(json.dumps(receipt,ensure_ascii=False,indent=2)+'\n');print(i,'context terminal complete; root two carriers byte-identical reused')
