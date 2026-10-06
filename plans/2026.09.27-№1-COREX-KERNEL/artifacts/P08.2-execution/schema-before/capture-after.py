#!/usr/bin/env python3
import json,os,pathlib,subprocess,sys
source=pathlib.Path(sys.argv[1]).resolve()
output=pathlib.Path(sys.argv[2]).resolve()
output.mkdir(parents=True,exist_ok=True)
harness=pathlib.Path(__file__).with_name('witness.php')
base=pathlib.Path(__file__).parent
psql=['psql','-h','127.0.0.1','-p','5434','-U','corex']
env=dict(os.environ,PGPASSWORD='corex')
for scope in ['central','tenant']:
    db=f'corex_p082_schema_after_{scope}_test'
    exists=subprocess.check_output(psql+['-d','postgres','-Atqc',f"SELECT 1 FROM pg_database WHERE datname='{db}'"],env=env,text=True).strip()
    if exists: raise SystemExit(f'{db} already exists; refusing implicit wipe/reuse')
    subprocess.run(['createdb','-h','127.0.0.1','-p','5434','-U','corex',db],env=env,check=True)
    runenv=dict(os.environ,PGSQL_HOST='127.0.0.1',PGSQL_PORT='5434',PGSQL_DATABASE=db,PGSQL_USERNAME='corex',PGSQL_PASSWORD='corex',WITNESS_SCOPE=scope,WITNESS_SOURCE=str(source),WITNESS_OUTPUT=str(output/f'catalog-{scope}.json'))
    with open(output/f'run-{scope}.log','w') as log:
        result=subprocess.run(['php8.4',str(harness)],env=runenv,stdout=log,stderr=subprocess.STDOUT)
    if result.returncode: raise SystemExit(f'Witness {scope} failed: see {output}/run-{scope}.log')
    raw=subprocess.check_output(['docker','compose','exec','-T','pgsql','pg_dump','-U','corex','--schema-only','--no-owner','--no-privileges','--no-comments',db],env=env,text=True)
    (output/f'schema-{scope}.sql').write_text(raw)
    norm='\n'.join(line for line in raw.splitlines() if not line.startswith(('\\restrict ','\\unrestrict ')))+'\n'
    (output/f'schema-{scope}-normalized.sql').write_text(norm)
    before=json.loads((base/f'catalog-{scope}.json').read_text())
    after=json.loads((output/f'catalog-{scope}.json').read_text())
    keys=['scope','sets','migration_files','migrated','columns','constraints','indexes']
    equality={key:before[key]==after[key] for key in keys}
    equality['pg_dump_schema']=norm==(base/f'schema-{scope}-normalized.sql').read_text()
    (output/f'comparison-{scope}.json').write_text(json.dumps(equality,indent=2)+'\n')
    print(scope,json.dumps(equality))
    if not all(equality.values()): raise SystemExit(1)
