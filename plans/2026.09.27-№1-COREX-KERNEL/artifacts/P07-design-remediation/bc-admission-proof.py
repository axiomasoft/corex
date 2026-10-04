#!/usr/bin/env python3
"""Exercise installed exact approval checker on isolated carrier. Fixture errors are NOT actual Roave results."""
from pathlib import Path
import json,tempfile,subprocess,hashlib
root=Path(__file__).resolve().parents[4];plan=Path('plans/2026.09.27-№1-COREX-KERNEL')
checks={}
def check(name,condition):
 checks[name]=bool(condition)
 if not condition:raise AssertionError(name)
with tempfile.TemporaryDirectory(prefix='corex-p07-bc-') as temp:
 t=Path(temp);(t/'scripts/bc').mkdir(parents=True)
 base=json.loads((root/'scripts/bc/accepted-base.json').read_text())['revision']
 paths=[str(plan/'decisions/D102-p07-rereview-contract-completeness.md'),str(plan/'decisions/D103-p07-stable-bc-admission-review.md')]
 decisions=[]
 for path in paths:
  dest=t/path;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes((root/path).read_bytes())
  decisions.append({'path':path,'sha256':hashlib.sha256(dest.read_bytes()).hexdigest()})
 # Deliberately a labelled fixture; future product step MUST capture real descriptions from Roave.
 errors=[{'path':'/packages/tenancy/src/Laravel/Http/Middleware/AccountStatusGate.php','modificationType':'changed','description':'FIXTURE ONLY: AccountStatusGate constructor optional policy -> required five arguments'}]
 rule={'base_revision':base,'decisions':decisions,'changes':[{'path':e['path'],'modificationType':e['modificationType'],'description_sha256':hashlib.sha256(e['description'].encode()).hexdigest()} for e in errors]}
 carrier={'schema':'corex-bc-approved-deltas/1','packages':{'axiomasoft/corex-tenancy':rule}}
 c=t/'scripts/bc/approved-deltas.json'
 def run(rows=errors,selected_base=base,selected_package='axiomasoft/corex-tenancy'):
  c.write_text(json.dumps(carrier))
  r=subprocess.run(['python3',str(root/'scripts/bc/approved-delta.py'),'--root',str(t),'--package',selected_package,'--base',selected_base],input=json.dumps({'errors':rows},separators=(',',':'))+'\n',capture_output=True,text=True)
  return r.returncode
 check('exact_fixture_decision_bound_set_accepted',run()==0)
 check('unknown_description_denied',run([{**errors[0],'description':'UNKNOWN BREAK'}])==1)
 check('additional_break_denied',run(errors+[{'path':None,'modificationType':'removed','description':'UNKNOWN'}])==1)
 check('empty_break_set_denied',run([])==1)
 check('wrong_base_denied',run(selected_base='wrong')==1)
 check('unlisted_package_denied',run(selected_package='axiomasoft/unknown')==1)
 first=t/paths[0];original=first.read_bytes();first.write_bytes(original+b'\nmutation\n')
 check('changed_decision_hash_denied',run()==1)
 first.write_bytes(original.replace(b'\nstatus: accepted\n',b'\nstatus: proposed\n'));decisions[0]['sha256']=hashlib.sha256(first.read_bytes()).hexdigest()
 check('nonaccepted_decision_even_with_matching_hash_denied',run()==1)
print(json.dumps({'passed':len(checks),'checks':checks,'checker_sha256':hashlib.sha256((root/'scripts/bc/approved-delta.py').read_bytes()).hexdigest(),'limits':'Actual unmodified checker; temp approval carrier and clearly labelled Roave-shaped fixture. No product approval, real Roave report, stable-gate GREEN or base mutation claimed.'},indent=2))
