from pathlib import Path
import sys,json,subprocess,hashlib
from datetime import datetime,timezone
from task import task_contract,plan_evidence,plan_recovery,plan_item_state,plan_status,plan_decisions,terminal_policy,journal
p=Path('plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING');a=p/'artifacts/B-P3-LOCAL';i=sys.argv[1];report={'P3.1':'findings/P3-storage-context.md','P3.2':'findings/P3-claim-maintenance.md'}[i]
results=json.loads((a/i/'checks/results.json').read_text())
for q in results:
 assert q['exit_code']==0,q
 assert hashlib.sha256((p/q['log']).read_bytes()).hexdigest()==q['sha256'],q
checks=[]
for cmd in [['python3',str(p/'scripts/validate-p3.py'),'--stage','branches','--report',report],['python3',str(p/'scripts/task-check.py'),'--check','design'],['python3',str(p/'scripts/task-check.py'),'--check','lint']]:
 t=datetime.now(timezone.utc).isoformat();r=subprocess.run(cmd,text=True,capture_output=True);q={'command':cmd,'cwd':str(Path.cwd()),'started_at':t,'ended_at':datetime.now(timezone.utc).isoformat(),'exit_code':r.returncode,'stdout':r.stdout,'stderr':r.stderr};checks.append(q);assert r.returncode==0,q
semantic={'status':'green','report':report,'sha256':hashlib.sha256((p/report).read_bytes()).hexdigest(),'review':'Required seven local cases have actual native/physical/risk command proofs; all frozen atomic obligations preserved, effective external obligations remain owning gates; exact source/adoption/unsupported modes explicitly classified.'}
actual=json.loads(Path('/tmp/corex-p3-route.json').read_text());c=task_contract.build(p,i,actual_route=actual);g=plan_evidence.compile_graph(p,i);v=task_contract.evaluate(c,evidence_graph=g,requires_closure_freshness=True);assert v['pre_mutation']=='allow' and v['pre_final']=='allow',v
f=a/f'{i}-check-results.json';f.write_text(json.dumps({'checks':checks,'risk_checks':results,'semantic':semantic,'write_site':v},ensure_ascii=False,indent=2)+'\n');h=hashlib.sha256(f.read_bytes()).hexdigest();validation=[{'carrier':' '.join(q['command']),'status':'green','evidence_ids':[str(f.relative_to(p)),h]} for q in results+checks]+[{'carrier':'semantic acceptance of seven local cases and preserved atomic/external obligations','status':'green','evidence_ids':[report,semantic['sha256']]}]
d=terminal_policy.build_decision(p,i,result='closed-green',validation=validation);r=json.loads((a/f'{i}-run.json').read_text());e=journal.new_entry(plan_id=p.name,item_id=i,command='plan-exec',actor='executor/gpt-5.6-sol/high',session_id=actual['thread_id'],run_id=r['run_id'],attempt=r['attempt'],result='closed-green',decision=d,note=f'Seven local cases/root D34/Arch/contracts/antiweakening/D98/package PASS. Native/physical proof; external effective gates stay pending. Receipt: artifacts/B-P3-LOCAL/{i}-check-results.json\nwrite-site: pre_mutation: allow · pre_final: allow');journal.append_idempotent_terminal(p,e)
plan_item_state.build_states(p);plan_status.build_status(p);plan_decisions.build_views(p);nextp=plan_recovery.materialize_next_ready(p,i)
g2=plan_evidence.compile_graph(p,i);anchor=lambda x:next(n['anchor'] for n in x['nodes'] if n['id']=='product');cl=plan_evidence.verify_closure(anchor(g),anchor(g2));(a/f'{i}-closure-freshness.json').write_text(json.dumps(cl,indent=2)+'\n');(a/f'{i}-pre-final.json').write_text(json.dumps(v,indent=2)+'\n');print(i,'closed-green; next bundle',nextp)
