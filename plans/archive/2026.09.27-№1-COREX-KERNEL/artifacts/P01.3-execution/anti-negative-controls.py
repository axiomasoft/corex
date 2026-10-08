import json,subprocess
from pathlib import Path
out=Path('plans/2026.09.27-№1-COREX-KERNEL/artifacts/P01.3-execution');results=[]
def run(name,expected,contains):
 r=subprocess.run(['bash','scripts/anti-weakening-gate.sh'],capture_output=True,text=True);txt=r.stdout+r.stderr;(out/(name+'.log')).write_text(txt)
 assert r.returncode==expected and contains in txt,(name,r.returncode,txt)
 results.append({'control':name,'exit':r.returncode,'contains':contains})
run('anti-initial-committed',0,'ANTI-WEAKENING GATE: OK')
for path,name,mutate in [('scripts/quality-ceilings.json','anti-ceiling-growth',lambda d:d['phpstan'].__setitem__('recurrence',d['phpstan']['recurrence']+1)),('scripts/ownership-gate-baseline.json','anti-ownership-growth',lambda d:d['violations'].append('O2 fake debt'))]:
 p=Path(path);saved=p.read_bytes()
 try:
  data=json.loads(saved);mutate(data);p.write_text(json.dumps(data)+'\n');run(name,1,'ratchet FAIL: '+path)
 finally:p.write_bytes(saved)
run('anti-restored',0,'ANTI-WEAKENING GATE: OK')
(out/'anti-negative-controls.json').write_text(json.dumps(results,indent=2)+'\n');print(json.dumps(results,indent=2))
