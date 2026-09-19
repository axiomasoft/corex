from pathlib import Path
import json,sys
from task import task_contract,plan_evidence,plan_admission,plan_recovery,route_evidence
p=Path('plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING');a=p/'artifacts/B-P2-PROPOSALS';i=sys.argv[1];actual=json.loads(Path('/tmp/corex-p2-route.json').read_text());ad=plan_admission.batch_admission(p,[i]);assert ad['verdict']=='ALLOW',ad
c=task_contract.build(p,i,actual_route=actual);g=plan_evidence.compile_graph(p,i);v=task_contract.evaluate(c,evidence_graph=g,requires_closure_freshness=True);assert v['pre_mutation']=='allow' and v['pre_final']=='allow',v
b=task_contract.build_batch_receipt(ad['batch'],completed_prefix=[f"P2.{k}" for k in range(1,int(i.split(".")[1]))]);r=plan_recovery.start(p,i,command='plan-exec',actor='executor/gpt-5.6-sol/high',session_id=actual['thread_id'],contract=c,batch_receipt=b)
for name,x in [('contract',c),('graph-before',g),('pre-mutation',v),('admission',ad),('run',r),('route-run',route_evidence.build_run_receipt(plan_id=p.name,item_id=i,attempt=r['attempt'],session_id=actual['thread_id'],run_id=r['run_id'],evidence=actual))]:
 (a/f'{i}-{name}.json').write_text(json.dumps(x,ensure_ascii=False,indent=2)+'\n')
print(i,r,v)
