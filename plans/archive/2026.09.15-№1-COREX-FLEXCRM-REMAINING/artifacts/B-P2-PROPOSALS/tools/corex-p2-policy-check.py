import importlib.util,json,contextlib,io,sys,hashlib
from pathlib import Path
from unittest.mock import patch
p=Path('plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING');runtime=Path('/home/vostrikov/projects/packages/swissknifeman/packages/task');sys.path.insert(0,str(runtime/'lib'))
s=importlib.util.spec_from_file_location('gate',p/'scripts/task-check.py');g=importlib.util.module_from_spec(s);s.loader.exec_module(g)
from task import plan_weight,plan_lint
measured=plan_weight.measure_plan(p,plan_weight.Measurer());row=next(x for x in measured['items'] if x['item_id']=='P2.1');w=row['weight_tokens'];base=f'read-budget-v1: пункт P2.1: вес {w} > порога 100000 оценочных токенов (negative guard fixture)'
cases=[('approved-aggregate',p/'plan.md',None,base,0),('foreign-P3',p/'plan.md',None,base.replace('P2.1','P3.1'),1),('per-read',p/'plan.md',None,'read-budget-v1: D2(b) oversized file',1),('digest',p/'plan.md',None,'bundle_digest_mismatch',1),('wrong-weight',p/'plan.md',None,base.replace(str(w),str(w+1)),1),('wrong-path',p/'roadmap.md',None,base,1),('wrong-line',p/'plan.md',1,base,1)]
results=[]
for name,path,line,msg,expected in cases:
 def lint(root,diagnostics):diagnostics.error(path,line,msg)
 with patch.object(plan_weight,'measure_plan',return_value=measured),patch.object(plan_lint,'lint_plan',side_effect=lint),contextlib.redirect_stdout(io.StringIO()) as out:
  actual=g.policy_lint(p,runtime)
 assert actual==expected,(name,actual,expected,out.getvalue())
 results.append({'case':name,'expected_exit':expected,'actual_exit':actual,'result':'PASS'})
f=p/'research/09-read-budget-policy.json';raw=f.read_bytes()
try:
 policy=json.loads(raw);policy['additional_scopes'][0]['scope'].append('P3.1');f.write_text(json.dumps(policy))
 try:g.policy_lint(p,runtime)
 except ValueError:results.append({'case':'scope-expansion','result':'PASS'})
 else:raise AssertionError('unapproved scope allowed')
finally:f.write_bytes(raw)
for scope,decision in [('D19','decisions/D19-read-budget-advisory.md'),('D20','decisions/D20-P2-read-budget-advisory.md')]:
 f=p/decision;raw=f.read_bytes()
 try:
  f.write_bytes(raw+b'changed')
  try:g.policy_lint(p,runtime)
  except ValueError:results.append({'case':scope+'-digest-drift','result':'PASS'})
  else:raise AssertionError('decision drift allowed')
 finally:f.write_bytes(raw)
(p/'artifacts/B-P2-PROPOSALS/D20-policy-checks.json').write_text(json.dumps({'result':'PASS','kind':'isolated negative diagnostic policy guard; not runtime acceptance','native_weight':w,'checks':results},indent=2)+'\n');print('Policy guard PASS:',len(results),'negative/positive cases; native weight',w)
