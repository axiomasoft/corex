exec(open('/tmp/corex-p2-common.py').read())
import shutil
from task import plan_recovery,task_contract,plan_evidence,plan_item_state,plan_status,plan_decisions,route_evidence
old=a/'P2.4-attempt1';old.mkdir(exist_ok=True)
for f in a.glob('P2.4-*'):
 if f.is_file():shutil.copyfile(f,old/f.name)
for name in ['P2-contract-catalogue.md','P2-gate-requests.md']:shutil.copyfile(p/'findings'/name,old/name)
actual=json.loads(Path('/tmp/corex-p2-route.json').read_text());c=task_contract.build(p,'P2.4',actual_route=actual);g=plan_evidence.compile_graph(p,'P2.4');boundary=task_contract.evaluate(c,evidence_graph=g,requires_closure_freshness=True);assert boundary['pre_mutation']==boundary['pre_final']=='allow',boundary
r=plan_recovery.start(p,'P2.4',command='plan-exec',actor='executor/gpt-5.6-sol/high',session_id=actual['thread_id'],contract=c,batch_receipt=json.loads((a/'P2.4-route-run.json').read_text()).get('batch_receipt')) if False else None
# build canonical admission batch from existing current owning receipt, retains complete P2 prefix.
ad=json.loads((a/'P2.4-admission.json').read_text());batch=task_contract.build_batch_receipt(ad['batch'],completed_prefix=['P2.1','P2.2','P2.3'])
r=plan_recovery.start(p,'P2.4',command='plan-exec',actor='executor/gpt-5.6-sol/high',session_id=actual['thread_id'],contract=c,batch_receipt=batch);assert r['allowed'];(a/'P2.4-run.json').write_text(json.dumps(r,indent=2)+'\n');receipt=route_evidence.build_run_receipt(plan_id=p.name,item_id='P2.4',attempt=r['attempt'],session_id=actual['thread_id'],run_id=r['run_id'],evidence=actual);route_evidence.verify_run_receipt(receipt,current_evidence=actual);(a/'P2.4-route-run.json').write_text(json.dumps(receipt,indent=2)+'\n');plan_item_state.build_states(p);plan_status.build_status(p);plan_decisions.build_views(p);print('P2.4 owning admission-format repair attempt',r['attempt'],r['run_id'])
