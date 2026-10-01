#!/usr/bin/env python3
"""Apply the sealed per-type classification; semantic seam changes are separate."""
import csv,re,json,hashlib,subprocess
from pathlib import Path
P=Path('plans/2026.09.27-№1-COREX-KERNEL')
rows=list(csv.DictReader((P/'artifacts/P04-design/classification.tsv').open(),delimiter='\t'))
changed=[]
for r in rows:
    if r['verdict'] not in ('Api','Spi call','Spi implement'): continue
    p=Path(r['path']); s=subprocess.check_output(['git','show','HEAD:'+str(p)],text=True)
    if re.search(r'#\[(?:\\CoreX\\Attributes\\)?(?:Api|Spi)\b',s): continue
    s=re.sub(r'(?m)^/\*\* @internal[^\n]*\*/\n', '', s)
    s=re.sub(r'(?m)^\s*\* @internal[^\n]*\n', '', s)
    s=s.replace('/** */\n','').replace('/**\n */\n','')
    attr="#[\\CoreX\\Attributes\\Api(since: '1.0')]" if r['verdict']=='Api' else "#[\\CoreX\\Attributes\\Spi(kind: \\CoreX\\Attributes\\SpiKind::"+('Call' if r['verdict']=='Spi call' else 'Implement')+", since: '1.0')]"
    s,n=re.subn(r'(?m)^((?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+)',lambda m:attr+'\n'+m[1],s,count=1)
    assert n==1,r
    p.write_text(s);changed.append(str(p))
(P/'artifacts/P04.2-execution/annotation-pass.json').write_text(json.dumps(changed,indent=2)+'\n')
print('annotated',len(changed))
