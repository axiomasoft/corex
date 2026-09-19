exec(open('/tmp/corex-p2-common.py').read())
f=p/'phases/P2/P2.4.md';b=f.read_text();raw=re.search(r'\*\*Required Reads:\*\* (.*?)(?=\n\*\*)',b,re.S)[1];parts=re.split(r'\d+\) ',raw)[1:];out=[];seen=False
for part in parts:
 part=part.strip().rstrip('.')
 if part.startswith('`findings/P2-contract-proposals.md`'):
  if not seen:out.append('`findings/P2-contract-proposals.md` '+', '.join('§'+s for s in ['1','0']+['2.'+str(j) for j in range(1,31)])+' (producer: P2.3)');seen=True
 else:out.append(part)
b=re.sub(r'\*\*Required Reads:\*\* .*?(?=\n\*\*)',lambda _:'**Required Reads:** '+' '.join(str(n)+') '+x+'.' for n,x in enumerate(out,1)),b,flags=re.S);f.write_text(b)
from task import plan_bundle
plan_bundle.build(p,'P2.4');print('P2.4 bundle materialized; declaration entries',len(out))
