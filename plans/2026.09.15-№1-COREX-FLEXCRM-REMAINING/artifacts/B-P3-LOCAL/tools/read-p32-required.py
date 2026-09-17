from pathlib import Path
import hashlib,json,sys
sys.path.insert(0,'/home/vostrikov/projects/packages/swissknifeman/packages/task/lib')
from task.plan_bundle import _selected_bytes
p=Path('/home/vostrikov/projects/packages/corex/plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING');a=p/'artifacts/B-P3-LOCAL';f=a/'P3.2-required-read-progress.json';d=json.loads(f.read_text())
if len(sys.argv)>1 and sys.argv[1]=='ack':
 pending=json.loads((a/'P3.2-pending-read-chunk.json').read_text());pending=pending if isinstance(pending,list) else [pending]
 for c in pending:
  e=d['entries'][c['index']];assert e['read_offset']==c['offset'];assert hashlib.sha256(Path(e['source']).read_bytes()).hexdigest()==e['sha256'];e['read_offset']=c['next_offset'];e['read_status']='complete' if e['read_offset']==e['bytes'] else 'partial';e.setdefault('received_chunks',[]).append(c)
 d['next_index']=next((x['index'] for x in d['entries'] if x['read_offset']!=x['bytes']),len(d['entries']));d['all_complete']=d['next_index']==len(d['entries']);f.write_text(json.dumps(d,ensure_ascii=False,indent=2)+'\n')
if d['all_complete']:print('ALL DECLARED READS COMPLETE');sys.exit(0)
receipts=[];outputs=[];budget=32000
for e in d['entries']:
 if e['read_offset']==e['bytes']:continue
 src=Path(e['source']);raw=src.read_bytes();assert hashlib.sha256(raw).hexdigest()==e['sha256'];sel=_selected_bytes(raw,e['selector'],src);assert hashlib.sha256(sel).hexdigest()==e['selected_sha256'];off=e['read_offset'];end=min(off+budget,len(sel))
 while True:
  try:content=sel[off:end].decode();break
  except UnicodeDecodeError:end-=1
 if end==off:break
 receipt={k:e[k] for k in ['index','declaration_ref','path','selector','sha256','selected_sha256','bytes']};receipt.update(offset=off,next_offset=end,chunk_sha256=hashlib.sha256(sel[off:end]).hexdigest(),complete=end==len(sel));receipts.append(receipt);outputs.extend([json.dumps(receipt,ensure_ascii=False),content]);budget-=end-off
 if budget<1000 or end!=len(sel):break
(a/'P3.2-pending-read-chunk.json').write_text(json.dumps(receipts,ensure_ascii=False,indent=2)+'\n');print('\n'.join(outputs))
