from pathlib import Path
import hashlib,json,os,subprocess,time,sys
out=Path('build/P14.21');out.mkdir(exist_ok=True,parents=True)
env=os.environ.copy();env.update(APP_ENV='testing',PGSQL_HOST='127.0.0.1',PGSQL_PORT='5434',PGSQL_DATABASE='corex_test',PGSQL_USERNAME='corex',PGSQL_PASSWORD='corex')
checks=[('root-lint',['composer','lint:check']),('root-analyse',['composer','analyse']),('root-test',['composer','test','--','--log-junit','build/P14.21/root-test.xml']),('root-pg',['composer','test:pg','--','--log-junit','build/P14.21/root-pg.xml'])]
for pkg in ['api','modules','transfer']:
 for script in ['lint:check','analyse','test']:
  args=['composer',f'--working-dir=packages/{pkg}',script]
  if script=='test':args+=['--','--log-junit',f'../../build/P14.21/{pkg}-test.xml']
  checks.append((f'{pkg}-{script.replace(":", "-")}',args))
checks.extend([('api-pg',['vendor/bin/pest','--configuration=phpunit.xml','packages/api/tests/Feature','--group=pg','--log-junit','build/P14.21/api-pg.xml']),('transfer-pg',['composer','--working-dir=packages/transfer','test:pg','--','--log-junit','../../build/P14.21/transfer-pg.xml']),('arch',['composer','test:arch']),('api-contract',['composer','api-contract']),('snapshot-compatibility',['php','scripts/check-api-contract.php','--baseline','build/P14.21/baseline/api/v1','--candidate','api/v1']),('package-graph',['composer','package-graph-gate']),('provider-size',['php','scripts/provider-size-gate.php']),('extension-contract',['php','scripts/extension-contract-gate.php']),('table-ownership',['composer','table-ownership-gate']),('contract-guard',['composer','contract-guard']),('mutations',['bash','scripts/mutation-check.sh','--all'])])
selected=sys.argv[1:]
manifest=json.loads((out/'checks.json').read_text()) if (out/'checks.json').exists() else []
for ident,cmd in checks:
 if selected and ident not in selected:continue
 start=time.time();log=out/f'{ident}-final.log'
 with log.open('wb') as stream:
  run=subprocess.run(cmd,env=env,stdout=stream,stderr=subprocess.STDOUT)
 outputs=[]
 for line in log.read_text(errors='replace').splitlines():
  try: val=json.loads(line)
  except ValueError:continue
  if isinstance(val,dict):outputs.append(val)
 row={'id':ident,'argv':cmd,'cwd':str(Path.cwd()),'environment':{k:env[k] for k in ['APP_ENV','PGSQL_HOST','PGSQL_PORT','PGSQL_DATABASE','PGSQL_USERNAME']},'exit_code':run.returncode,'duration_ms':round(1000*(time.time()-start)),'log':str(log),'log_sha256':hashlib.sha256(log.read_bytes()).hexdigest(),'results':outputs}
 manifest.append(row);(out/'checks.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n')
 print(json.dumps({k:v for k,v in row.items() if k not in ['environment','results','argv','cwd']},ensure_ascii=False),flush=True)
 for val in outputs:print(json.dumps({k:v for k,v in val.items() if 'details' not in k and k not in ['failures','instructions']},ensure_ascii=False),flush=True)
 if run.returncode:
  print(log.read_text(errors='replace')[-2500:],flush=True)
  sys.exit(run.returncode)
