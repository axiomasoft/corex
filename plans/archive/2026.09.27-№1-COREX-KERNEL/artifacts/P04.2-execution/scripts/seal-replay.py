from pathlib import Path
import json,subprocess,hashlib
r=Path.cwd();a=r/'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P04.2-execution'
h=lambda p: hashlib.sha256(p.read_bytes()).hexdigest() if p.is_file() else None
changed=subprocess.check_output(['git','diff','--name-only','-z','HEAD']).decode().split('\0')
new=subprocess.check_output(['git','ls-files','--others','--exclude-standard','-z']).decode().split('\0')
changed=[p for p in changed if p and not p.startswith('plans/')];new=[p for p in new if p and not p.startswith('plans/')]
patch=subprocess.check_output(['git','diff','--binary','HEAD','--',*changed])
for p in new:
 d=subprocess.run(['git','diff','--no-index','--binary','--','/dev/null',p],capture_output=True);assert d.returncode==1;patch+=d.stdout
(a/'candidate.patch').write_bytes(patch)
m={'schema':'p04-sealed-replay/v1','preimage_commit':subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip(),'patch_sha256':hashlib.sha256(patch).hexdigest(),'paths':{p:h(r/p) for p in sorted(set(changed+new))},'review':'Independent review in progress; code remains source of truth','mapped_classes':len(json.loads((a/'final-move-map.json').read_text())['classes'])}
(a/'transformation-manifest.json').write_text(json.dumps(m,indent=2,ensure_ascii=False)+'\n');print('sealed',len(m['paths']),'real paths',m['patch_sha256'])
