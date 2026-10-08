#!/usr/bin/env python3
import csv,json,re
from pathlib import Path
P=Path('plans/2026.09.27-№1-COREX-KERNEL')
rows=list(csv.DictReader((P/'artifacts/P04-design/classification.tsv').open(),delimiter='\t'))
classes={};moves=[]
for r in rows:
 if r['verdict']=='consumer-only':continue
 old=r['symbol'];new=r['target']
 if r['verdict']=='replace':
  bits=old.split('\\');path=Path(r['path']);src=path.parts.index('src');dirs=list(path.parts[src+1:-1]);module=dirs[0] if r['package']=='axiomasoft/corex' or (dirs and dirs[0] not in ('Contracts','Data','Enums','Events','Exceptions','Internal','Laravel','Testing','PHPStan')) else None
  ns=old.split('\\')[:len(bits)-len(dirs)-1]; inner=dirs[1:] if module else dirs
  if module: ns+=[module]
  ns+=['Internal']+inner
  if inner and inner[0] not in ('Models','Contracts','Compiler','Discovery'):ns=ns[:-(len(inner))]
  new='\\'.join(ns+[bits[-1]])
 if old!=new:
  path=Path(r['path']);root=Path(*path.parts[:path.parts.index('src')+1]);manifest=json.loads((root.parent/'composer.json').read_text());prefix=next(ns for ns,p in manifest['autoload']['psr-4'].items() if p.rstrip('/')=='src')
  target=root/Path(new[len(prefix):].replace('\\','/')+'.php');assert not target.exists(),target
  classes[old]=new;moves.append({'old':old,'new':new,'path':str(path),'target_path':str(target),'verdict':r['verdict'],'public_target':r['target']})
out=P/'artifacts/P04.2-execution';(out/'move-map.json').write_text(json.dumps({'classes':classes,'moves':moves},indent=2)+'\n');print(len(moves),'moves')
