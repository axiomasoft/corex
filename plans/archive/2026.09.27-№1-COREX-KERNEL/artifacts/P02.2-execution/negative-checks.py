#!/usr/bin/env python3
"""Run bounded probes serially, restoring exact source and generated edge bytes."""
from pathlib import Path
import subprocess,json,os
P=Path('plans/2026.09.27-№1-COREX-KERNEL');A=P/'artifacts/P02.2-execution'
files=[Path('packages/operations/src/OpenApi/Internal/ContractSnapshotBuilder.php'),Path('packages/operations/src/Webhooks/WebhooksServiceProvider.php'),Path('packages/operations/composer.json'),P/'artifacts/P00/dependency-edges.tsv']
original={p:p.read_bytes() for p in files}
def run(name,cmd):
 with (A/(name+'.log')).open('w') as f:r=subprocess.run(cmd,stdout=f,stderr=subprocess.STDOUT,env={**os.environ,'GOLDEN_UPDATE':'0'})
 return dict(exit=r.returncode,log=name+'.log')
results={}
try:
 p=files[0];p.write_text(p.read_text().replace('use CoreX\\Api\\Contracts\\ContractSnapshotSection;','use CoreX\\Api\\Data\\WebhookTopic;\nuse CoreX\\Api\\Contracts\\ContractSnapshotSection;'))
 run('negative-a-edges',['python3',str(P/'artifacts/P00-design/scripts/check-maps.py'),'--write-edges'])
 results['a']=run('negative-a',['python3',str(P/'artifacts/P00-design/scripts/check-maps.py'),'--dependencies'])
 assert results['a']['exit']==1
 assert 'dependency-cycle' in (A/'negative-a.log').read_text() or 'internal-edge' in (A/'negative-a.log').read_text()
 p.write_bytes(original[p]);files[3].write_bytes(original[files[3]])
 results['a-restored']=run('negative-a-restored',['python3',str(P/'artifacts/P00-design/scripts/check-maps.py'),'--dependencies']);assert results['a-restored']['exit']==0
 p=files[1];s=p.read_text();needle='$this->app->tag([WebhookSnapshotSection::class], ContractSnapshotSection::class);';assert needle in s;p.write_text(s.replace(needle,'// Snapshot tag intentionally removed by the negative probe.'))
 results['b']=run('negative-b',['scripts/php84','vendor/bin/pest','--testsuite=Golden','--filter=wire']);assert results['b']['exit']!=0
 assert 'Missing snapshot:' in (A/'negative-b.log').read_text()
 p.write_bytes(original[p]);results['b-restored']=run('negative-b-restored',['scripts/php84','vendor/bin/pest','--testsuite=Golden','--filter=wire']);assert results['b-restored']['exit']==0
 p=files[2];s=json.loads(p.read_text());s['extra']['laravel']['providers']=[];p.write_text(json.dumps(s,indent=4)+'\n')
 results['c']=run('negative-c',['scripts/php84','vendor/bin/pest','--testsuite=Golden','--filter=routes']);assert results['c']['exit']!=0
 p.write_bytes(original[p]);results['c-restored']=run('negative-c-restored',['scripts/php84','vendor/bin/pest','--testsuite=Golden','--filter=routes']);assert results['c-restored']['exit']==0
finally:
 for p,body in original.items():p.write_bytes(body)
 (A/'negatives.json').write_text(json.dumps(results,ensure_ascii=False,indent=2)+'\n')
print(json.dumps(results,ensure_ascii=False))
