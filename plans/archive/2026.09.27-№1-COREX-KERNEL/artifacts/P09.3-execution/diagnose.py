#!/usr/bin/env python3
import os,sys,json,pathlib,subprocess,collections
out=pathlib.Path(__file__).resolve().parent
names=sys.argv[1:] or ['custom-fields','intl','media','notifications','panels','recurrence','search','search-intl','search-meilisearch','tasks','tasks-workflow','transfer','wakeup','workflow','flex-crm']
def diagnose(name):
 c=pathlib.Path('products' if name=='flex-crm' else 'packages')/name/'phpstan.neon'
 temp=c.parent/'phpstan-p093.neon';temp.write_text(c.read_text().replace('    - phpstan-baseline.neon\n',''))
 try:
  p=subprocess.run(['scripts/php84','vendor/bin/phpstan','analyse','-c',str(temp),'--memory-limit=2G','--no-progress','--error-format=json'],env={**os.environ,'PAO_DISABLE':'1'},capture_output=True,text=True)
  (out/f'current-{name}.json').write_text(p.stdout);(out/f'current-{name}.stderr').write_text(p.stderr)
  d=json.loads(p.stdout);print(name,p.returncode,d['totals'],flush=True)
  initial=out/f'full-initial-{name}.json'
  if not initial.exists():initial.write_text(p.stdout)
 finally:temp.unlink()

from concurrent.futures import ThreadPoolExecutor
with ThreadPoolExecutor(max_workers=3) as pool:
 list(pool.map(diagnose,names))
