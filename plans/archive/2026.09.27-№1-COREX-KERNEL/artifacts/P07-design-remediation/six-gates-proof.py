#!/usr/bin/env python3
"""Execute proposed metadata checks with the installed parser in scratch files only."""
from pathlib import Path
import re,json,tempfile,subprocess,hashlib
root=Path(__file__).resolve().parents[4]
plan=Path(__file__).resolve().parents[2]
examples=(plan/'artifacts/P07-design/examples.md').read_text()
code=next(c for c in re.findall(r'```python\n(.*?)\n```',examples,re.S) if 'UNRELEASED' in c).replace('...\n','')
checks={}
for label,name,value,expected in [('bridge','axiomasoft/corex-access-azguard','unreleased',True),('foreign','axiomasoft/corex-access','unreleased',False),('unknown','axiomasoft/corex-access-azguard','mystery',False),('stable','axiomasoft/corex-access','stable',True)]:
    errors=[]
    env={'data':{'name':name,'extra':{'corex':{'stability':value}}},'is_root':False,'bad':lambda a,b:errors.append((a,b))}
    exec(code,env)
    assert (not errors)==expected,(label,errors)
    checks['manifest_'+label]=True
source=(root/'scripts/package-graph-gate.php').read_text()
function=re.search(r'function parseExtraCorex\(.*?\n}\n',source,re.S)[0]
constants="const COREX_SCHEMA_VERSION=1;const EXIT_CONFIG=2;const VALID_PACKAGE_TYPES=['foundation','capability','integration','product','tooling','fixture'];"
# Same enum declared in normative Python example; graph/taxonomy synchronized by D100.
new_enum=sorted(env['VALID_STABILITY'])
with tempfile.TemporaryDirectory(prefix='p07-gates-') as tmp:
    for mode,values in [('baseline',['stable','experimental','internal','deprecated']),('candidate',new_enum)]:
        for value in ['unreleased','mystery','stable']:
            data={'extra':{'corex':{'schema-version':1,'package-type':'integration','stability':value}}}
            php="<?php\n"+constants+'const VALID_STABILITY='+json.dumps(values).replace('[','[').replace(']',']')+';'+function+"\nparseExtraCorex(json_decode("+repr(json.dumps(data))+",true),'packages/access-azguard/composer.json');"
            f=Path(tmp)/'parser.php';f.write_text(php)
            r=subprocess.run([str(root/'scripts/php84'),'php',str(f)],capture_output=True,text=True,cwd=root)
            expected=0 if value=='stable' or (mode=='candidate' and value=='unreleased') else 2
            assert r.returncode==expected,(mode,value,r.stderr)
            checks[f'graph_{mode}_{value}']=True
spec=(plan/'phases/P07/P07.4.md').read_text()
assert '`scripts/package-graph-gate.php`' in spec and '`docs/architecture/taxonomy.md`' in spec
checks['gate_and_taxonomy_in_P07_4_Files']=True
print(json.dumps({'checks':checks,'passed':len(checks),'example_sha256':hashlib.sha256(examples.encode()).hexdigest(),'parser_sha256':hashlib.sha256(source.encode()).hexdigest(),'limits':'Parser/metadata only; full candidate graph requires future product implementation.'},indent=2))
