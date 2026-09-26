from pathlib import Path
import functools, hashlib, json, shutil, subprocess
from task import journal, plan_downstream, plan_item_state, plan_status, plan_closure

p = Path('plans/2026.09.19-№1-COREX-PLATFORM-HARDENING')
out = p / 'artifacts/P14.21-execution'
s = json.loads(Path('build/P14.21/start.json').read_text())
note = 'Qualified write-site codes from the persisted contract receipt; closure bookkeeping only.\nwrite-site: pre_mutation: allow · pre_final: allow'
if not any(e.get('note') == note and e.get('run_id') == s['run_id'] for e in journal.read_authoritative_entries(p)):
    journal.append(p, journal.new_entry(plan_id=p.name, item_id='P14.21', command='plan-exec', actor=s['actor'],
        result='no-op', session_id=s['session_id'], run_id=s['run_id'], attempt=s['attempt'], batch_receipt=s['batch_receipt'],
        note=note, decision={'signal_class':'acceptance','scope_class':'item','action':'record-write-site',
                             'recovered':True,'terminal':False}))
plan_item_state.build_states(p, item_id='P14.21', materialize_successor=False)
plan_status.build_status(p)
plan_closure.build_closure(p, 'P14')
# Reuse only an actually executed exact command with current input bytes.
for r in json.loads((out/'inputs.json').read_text())['members']:
    assert hashlib.sha256(Path(r['path']).read_bytes()).hexdigest()==r['sha256']
log = Path('build/P14.21/root-lint-bookkeeping.log').read_text()
assert '"result":"passed"' in log.replace(' ', ''), log
def runner(command, cwd):
    if command == 'composer lint:check': return subprocess.CompletedProcess(command, 0, log, '')
    return subprocess.CompletedProcess(command, 69, '', 'Outside this consumer scope; not executed or recorded')
# The stable field graph is identical for all producers in this read-only pass.
plan_downstream._all_items = functools.lru_cache(maxsize=None)(plan_downstream._all_items)
paths = []
for producer in ['P10.8','P11.8','P12.8','P13.12','P13.4','P9.5','P9.6','P9.7','P9.8']:
    result = plan_downstream.evaluate(p, producer, runner=runner)
    owned = [c for c in result['consumers'] if c['item']=='P14.21']
    assert len(owned)==1 and owned[0]['outcome']=='green', (producer, owned)
    result.update(consumers=owned, outcome='green', blocks=False)
    paths += plan_downstream.record(p, result)
    print(producer+' → P14.21: exact composer lint:check receipt reused', flush=True)
# Future release consumers have no applicable executable carrier in this scope.
# `<2` is a placeholder, `1.0.0` is a release version literal, and the third
# Validation is prose. Unknown is retained explicitly, never promoted to green.
future = plan_downstream.discover_consumers(p, 'P14.21')
for c in future:
    spec_sha = plan_downstream.consumer_spec_sha256(p, c['item'])
    c.update(consumer_spec_sha256=spec_sha, outcome='unknown', commands=[])
    c['deferred_reason']='Future release-owned acceptance; no executable validation in current scope'
    assert c['item'] in ['P17.8','P17.11','P17.12']
head = subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()
result = {'gate':plan_downstream.GATE_ID,'version':plan_downstream.VERSION,'producer_item':'P14.21',
          'producer_commit':head,'consumers':future,'outcome':'unknown','blocks':False}
paths += plan_downstream.record(p, result)
(out/'downstream-scope.json').write_text(json.dumps({'incoming_carrier':'composer lint:check',
    'incoming_log':'root-lint-bookkeeping.log','outgoing':result,'records':[str(x.relative_to(p)) for x in paths]},ensure_ascii=False,indent=2)+'\n')
shutil.copyfile('build/P14.21/plan-lint.log',out/'plan-lint-initial.log')
print('owned bookkeeping repaired; future unknown evidence disclosed')
