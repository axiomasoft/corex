#!/usr/bin/env python3
"""Run the named P02.3 acceptance carriers serially and retain exact outputs."""
import argparse,json,os,subprocess,time
from pathlib import Path
ap=argparse.ArgumentParser();ap.add_argument('--out',required=True);ap.add_argument('--fail-fast',action='store_true');ap.add_argument('checks',nargs='+');a=ap.parse_args()
D=Path(a.out);D.mkdir(parents=True,exist_ok=True)
commands={
    'manifests':['python3','scripts/manifest-check.py'],
    'root-test':['scripts/php84','composer','test'],
    'pg-test':['scripts/php84','composer','test:pg'],
    'packages-test':['scripts/php84','composer','test:packages'],
    'golden':['scripts/php84','composer','golden'],
    'analyse':['scripts/php84','composer','analyse'],
    'deps-analyse':['scripts/php84','composer','deps:analyse'],
    'ownership':['scripts/php84','php','scripts/ownership-gate.php'],
    'ceilings':['scripts/php84','php','scripts/quality-ceiling-guard.php'],
    'anti-weakening':['bash','scripts/anti-weakening-gate.sh'],
    'rename':['scripts/php84','php','scripts/rename-completeness-gate.php','--sections=classes,namespaces,packages,paths'],
    'lint':['scripts/php84','composer','lint:check'],
    'mutation':['scripts/php84','bash','scripts/mutation-check.sh','--all'],
}
for name in ['package-graph-gate','api-contract','contract-guard','naming-gate','package-docs-gate']:
    commands[name]=['scripts/php84','composer',name]
results=[]
for name in a.checks:
    start=time.monotonic();cmd=commands[name]
    with (D/(name+'.log')).open('w') as f:
        r=subprocess.run(cmd,stdout=f,stderr=subprocess.STDOUT,env={**os.environ,'PGSQL_PORT':'5432','XDEBUG_MODE':'off'})
    row={'check':name,'command':cmd,'exit':r.returncode,'seconds':round(time.monotonic()-start,2),'log':name+'.log'}
    results.append(row);(D/'checks.json').write_text(json.dumps(results,indent=2)+'\n');print(json.dumps(row),flush=True)
    if r.returncode and a.fail_fast:
        raise SystemExit(r.returncode)
