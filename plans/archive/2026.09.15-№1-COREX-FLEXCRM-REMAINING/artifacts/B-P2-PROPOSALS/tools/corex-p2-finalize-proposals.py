exec(open('/tmp/corex-p2-common.py').read())
from task import token_estimate,plan_bundle,plan_item_state,plan_status,plan_decisions
f=p/'findings/P2-contract-proposals.md';b=f.read_text()
# Final migration complete SQLite copy statement, exact column list and same constraints/index.
sec=re.search(r'^## C-P2-010\n(.*?)(?=^## |\Z)',b,re.M|re.S)[1];ddl=next(x for x in re.findall(r'```sql\n(.*?)\n```',sec,re.S) if x.startswith('CREATE TABLE ntf_deliveries_new'))
columns='id,notification_id,digest_run_id,channel,delivery_key,state,claim_epoch,attempts,next_attempt,lease_until,admitted_revision,provider_ref,error,sent_at,message,message_hash,template_version,policy_version,driver_version'
copy='BEGIN IMMEDIATE;\n'+ddl.rstrip()+'\nINSERT INTO ntf_deliveries_new ('+columns+') SELECT '+columns+' FROM ntf_deliveries;\nDROP TABLE ntf_deliveries;\nALTER TABLE ntf_deliveries_new RENAME TO ntf_deliveries;\nCREATE INDEX ntf_deliveries_scan_idx ON ntf_deliveries (state,next_attempt);\nCOMMIT;\n'
insert='Exact full SQLite final000005 migration transaction, foreign_keys=ON on the isolated installed migration connection; failed copy rolls back on any orphan/check/constraint violation. Before rename verify counts and selected fingerprint in the owning migration receipt, never provider boot:\n\n```sql\n'+copy.rstrip()+'\n```\n\n'
pos=b.index('## C-P2-011\n');b=b[:pos]+insert+b[pos:]
pos=b.index('## C-P2-012\n');b=b[:pos]+'Finite P4.7 migration placement: existing allowed150000 file creates scope-lock table FIRST then views with the composite own FK using the exact SQL variant above; allowed150001 file verifies the already-created scope-table fingerprint and performs no second CREATE. No forward FK to a nonexistent scope table, no consumer FK. Both opt-in migration paths remain those indexed by P4.7; this proposal does not write their runtime code.\n\n'+b[pos:]
# Numeric top-level groups encompass multiple exact ## C-ID sections with unchanged bytes.
pre,body=b.split('## C-P2-001\n',1);sections=re.findall(r'^## C-P2-\d{3}\n.*?(?=^## C-P2-\d{3}\n|\Z)','## C-P2-001\n'+body,re.M|re.S);assert len(sections)==30
groups=[];chunk=''
for section in sections:
 if chunk and token_estimate.estimate_text(chunk+section)>20000:groups.append(chunk);chunk=''
 chunk+=section
if chunk:groups.append(chunk)
assert all(token_estimate.estimate_text(x)<=25000 and len(x.encode())<=262144 for x in groups)
b=pre+''.join('# 3.'+str(n)+' — Source-complete proposal read group\n\n'+x for n,x in enumerate(groups,1));f.write_text(b)
f=p/'phases/P2/P2.4.md';b=f.read_text();b=re.sub(r'\*\*Inputs:\*\* .*?(?=\n\*\*)','**Inputs:** P2.3 → `findings/P2-contract-proposals.md` §0. All other original input sources remain source-complete in Required Reads below; duplicate declarations removed, no source/check/limit relaxed.',b,flags=re.S)
raw=re.search(r'\*\*Required Reads:\*\* (.*?)(?=\n\*\*)',b,re.S)[1];parts=re.split(r'\d+\) ',raw)[1:];out=[]
for x in parts:
 x=x.strip().rstrip('.')
 if x.startswith('`findings/P2-contract-proposals.md`'):x='`findings/P2-contract-proposals.md` '+', '.join('§'+s for s in ['1','0']+['3.'+str(n) for n in range(1,len(groups)+1)])+' (producer: P2.3)'
 out.append(x)
b=re.sub(r'\*\*Required Reads:\*\* .*?(?=\n\*\*)',lambda _:'**Required Reads:** '+' '.join(str(n)+') '+x+'.' for n,x in enumerate(out,1)),b,flags=re.S);f.write_text(b)
plan_bundle.build(p,'P2.4');plan_item_state.build_states(p);plan_status.build_status(p);plan_decisions.build_views(p)
(a/'P2.3-proposal-read-groups.json').write_text(json.dumps({'status':'source-complete','group_estimated_tokens':[token_estimate.estimate_text(x) for x in groups],'mandatory_sources_unchanged':True,'per_read_limits_unchanged':True,'P2.4_inputs_duplicate_removal_only':True,'exact_SQLite_final_migration':copy},indent=2)+'\n');print('Complete bounded proposal groups',len(groups),[token_estimate.estimate_text(x) for x in groups])
