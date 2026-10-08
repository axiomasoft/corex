import json,os,subprocess,tempfile
from pathlib import Path
repo=Path.cwd();out=repo/'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P01.3-execution';results=[]
with tempfile.TemporaryDirectory(prefix='corex-p013-install-') as tmp:
 root=Path(tmp);subprocess.run(['git','init','-q',tmp],check=True)
 for path in ['scripts/distribution','packages/tenancy','packages/access','vendor/bin','fakebin']:(root/path).mkdir(parents=True,exist_ok=True)
 (root/'scripts/dependency-analyser.sh').write_bytes((repo/'scripts/dependency-analyser.sh').read_bytes())
 (root/'scripts/distribution/stage-stancl-providers.py').write_text('''import json,os,sys\nfrom pathlib import Path\nif '--portable-locks' not in sys.argv:\n for path in ['composer.json','packages/tenancy/composer.json','packages/access/composer.json']:\n  p=Path(path);d=json.loads(p.read_text());d.setdefault('repositories',{})['stancl-providers']={'type':'artifact','url':'staged'};p.write_text(json.dumps(d))\n sys.exit(41 if os.environ['CONTROL_MODE']=='stage-failure' else 0)\n''')
 composer=root/'fakebin/composer';composer.write_text('''#!/usr/bin/env python3\nimport json,os,sys\nfrom pathlib import Path\nargs=sys.argv[1:];p=Path('calls.jsonl');p.open('a').write(json.dumps(args)+'\\n')\nmode=os.environ['CONTROL_MODE']\nif 'update' in args:sys.exit(42 if mode=='install-failure' else 0)\nif '--unset' in args:\n directory=next((a.split('=',1)[1] for a in args if a.startswith('--working-dir=')),'.')\n manifest=Path(directory)/'composer.json';d=json.loads(manifest.read_text());d.get('repositories',{}).pop('stancl-providers',None);manifest.write_text(json.dumps(d))\n sys.exit(43 if mode=='cleanup-failure' and directory=='.' else 0)\n''');composer.chmod(0o755)
 analyser=root/'vendor/bin/composer-dependency-analyser';analyser.write_text('#!/usr/bin/env bash\ntouch analyser-ran\n');analyser.chmod(0o755)
 for mode,expected,run_analyser in [('stage-failure',41,False),('install-failure',42,False),('cleanup-failure',43,False),('success',0,True)]:
  for p in ['composer.json','packages/tenancy/composer.json','packages/access/composer.json']:(root/p).write_text('{}')
  for p in ['calls.jsonl','analyser-ran']:(root/p).unlink(missing_ok=True)
  env={**os.environ,'PATH':str(root/'fakebin')+':'+os.environ['PATH'],'CONTROL_MODE':mode}
  r=subprocess.run(['bash','scripts/dependency-analyser.sh','tenancy'],cwd=root,env=env,capture_output=True,text=True)
  calls=[json.loads(x) for x in (root/'calls.jsonl').read_text().splitlines()]
  clean=all('stancl-providers' not in json.loads((root/p).read_text()).get('repositories',{}) for p in ['composer.json','packages/tenancy/composer.json','packages/access/composer.json'])
  assert r.returncode==expected and (root/'analyser-ran').exists()==run_analyser and clean,(mode,r,calls)
  assert sum('--unset' in x for x in calls)==3
  if mode=='stage-failure':assert all('update' not in x for x in calls)
  results.append({'case':mode,'exit':r.returncode,'analyser_ran':run_analyser,'all_three_roots_clean':clean,'calls':calls})
(out/'dependency-failclosed.json').write_text(json.dumps(results,indent=2)+'\n');print(json.dumps(results,indent=2))
