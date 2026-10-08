#!/usr/bin/env python3
import os,sys,subprocess,pathlib,json,time,hashlib
out=pathlib.Path(__file__).resolve().parent
checks={
'V1':['scripts/php84','composer','analyse'],
'V2':['scripts/php84','composer','analyse:tests-lint'],
'V3':['scripts/php84','php','scripts/quality/release-quality.php','--stage=all'],
'V4':['scripts/php84','composer','deps:analyse'],
'V5':['scripts/php84','composer','test:packages'],
'V6':['scripts/php84','composer','test:pg'],
'V7':['scripts/php84','composer','lint:check'],
'V8':['scripts/php84','composer','bc-check'],
'V9':['scripts/php84','composer','php-api'],
'V10':['scripts/php84','composer','schema-gate'],
'V11':['scripts/php84','composer','golden'],
'V12':['scripts/php84','bash','scripts/mutation-check.sh','--all'],
}
for key in sys.argv[1:]:
 cmd=checks[key];start=time.monotonic()
 with (out/f'{key}.log').open('w') as f:
  p=subprocess.run(cmd,stdout=f,stderr=subprocess.STDOUT,env={**os.environ,'PGSQL_PORT':'5434'})
 record={'id':key,'command':cmd,'exit':p.returncode,'duration_seconds':round(time.monotonic()-start,3),'log_sha256':hashlib.sha256((out/f'{key}.log').read_bytes()).hexdigest()}
 (out/f'{key}-receipt.json').write_text(json.dumps(record,indent=2)+'\n');print(json.dumps(record),flush=True)
 if p.returncode:print((out/f'{key}.log').read_text()[-5000:],flush=True)
