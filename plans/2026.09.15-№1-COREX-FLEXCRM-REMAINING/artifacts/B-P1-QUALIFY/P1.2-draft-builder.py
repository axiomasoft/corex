from pathlib import Path
import re,json,hashlib
p=Path(__file__).resolve().parents[2]
trace=[]
for l in (p/'research/01-traceability.md').read_text().splitlines():
 if re.match(r'^\| R\d\d \|',l):
  c=[x.strip() for x in l.split('|')[1:-1]];trace.append(c)
rs={c[0]:c for c in trace}
criteria=[]
for l in (p/'research/01a-acceptance-index.md').read_text().splitlines():
 if l.startswith('| ['):
  m=re.fullmatch(r'\| \[([^]]+)\]\(([^)]+)\)(.*?) \| (.*?) \| (.*) \|',l);assert m,l
  label,target,loc,orig,text=m.groups();criteria.append({'label':label,'path':(Path('research')/target).as_posix(),'location':loc.strip(),'original_r':orig,'original_text':text,'original_sha256':hashlib.sha256(text.encode()).hexdigest()})
assert len(criteria)==365
# Explicit source-level requirement assignment; historical program mappings stay intact.
def primary(b,n):
 if b==10:return 'R48' if 26<=n<=30 else 'R36' if n==34 else 'R52' if n in (31,32) else 'R50'
 if b==11:
  if n in (17,18,19,20):return 'R43'
  if 21<=n<=27:return 'R51'
  if n in (28,29,30,32):return 'R52'
  if n in (14,15,16,31):return 'R40'
  return 'R39'
 if b==12:return 'R10' if n in (13,14,15,20) else 'R09'
 if b==13:return 'R17' if 20<=n<=24 else 'R42' if 25<=n<=29 or n==41 else 'R27' if 30<=n<=38 else 'R30' if n==39 else 'R41' if n==40 else 'R12' if n==42 else 'R18'
 if b==15:return 'R32' if n==28 else 'R33' if n==30 else 'R11'
 if b==16:return 'R15' if n==12 else 'R16' if n in (14,15) else 'R10' if n==16 else 'R18' if n==17 else 'R14' if n in (1,20) else 'R37' if n in (7,8,9,10,11) else 'R38'
 if b==18:return 'R12' if n in (1,2,3,4,5,6,12,25) else 'R33' if n==21 else 'R13'
 if b==27:return 'R20' if n==3 else 'R19'
 if b==28:return 'R21'
 if b==29:return 'R20' if n==5 else 'R22'
 if b==30:return 'R29'
 if b==31:return 'R28' if n==14 else 'R31'
 if b==32:return 'R36' if n in (7,12) else 'R51' if n in (8,9,10) else 'R27'
 if b==33:return 'R01' if n in (5,6,7,14) else 'R39'
 if b==34:return 'R52' if n==7 else 'R07' if n in (6,10) else 'R06' if n in (5,8) else 'R03' if n in (2,3,4) else 'R40'
 if b==35:return 'R23' if n in (2,7,10) else 'R24' if n==12 else 'R32' if n==5 else 'R25' if n in (6,8) else 'R52' if n==11 else 'R26'
 if b==36:return 'R34'
 raise ValueError((b,n))
core={'R01','R02','R03','R04','R05','R06','R07','R08','R10','R11','R12','R14','R15','R16','R17','R20','R23','R24','R25','R28','R32','R33','R36','R45','R47','R50'}
standalone={'R09','R27','R51'};ops={'R39','R40','R46'};excluded={'R30','R48','R49'}
def boundary(r):return 'corex' if r in core else 'standalone' if r in standalone else 'external-ops' if r in ops else 'excluded' if r in excluded else 'cross-cutting' if r=='R52' else 'app'
def clauses(s):
 # Source slices, not summaries: never split inside code, brackets or parentheses.
 parts=[];start=0;code=False;depth=0
 for i,c in enumerate(s):
  if c=='`':code=not code
  if not code:
   if c in '([{':depth+=1
   elif c in ')]}':depth=max(0,depth-1)
   elif c==';' and not depth:parts.append(s[start:i].strip());start=i+1
 parts.append(s[start:].strip());return [a for a in parts if a]
# These mixed parents need explicit assignments beyond the source default.
assignments={
 'B11 AC-1':['R39','R51'], 'B11 AC-14':['R40','R40','R40','R03'],
 'B12 AC-1':['R09','R31','R39'],
 'B13 AC-40':['R41'], 'B13 AC-42':['R12','R18'],
 'B15 AC-26':['R11','R36'], 'B15 AC-27':['R11','R11','R11'],
 'B16 AC-1':['R37','R14'], 'B16 AC-20':['R14'],
 'B18 AC-2':['R12','R12'], 'B18 AC-6':['R12','R13'],
 'B18 AC-12':['R12','R13'], 'B18 AC-21':['R33','R12','R39'],
 'B33 AC-1':['R39','R39','R39'], 'B33 AC-7':['R01'],
 'B34 AC-5':['R06','R46','R40'], 'B34 AC-8':['R06','R40'],
 'B35 AC-1':['R25','R40'], 'B35 AC-13':['R26','R26'],
}
records=[];draft_findings=[]
for c in criteria:
 b,n=map(int,re.fullmatch(r'B(\d+)(?: AC-|\.§8\.)(\d+)',c['label']).groups());body=c['original_text'];span=None
 if b==36:
  src=(p/'brief/sources/b36.md').read_text();section=src[src.index('### 8.5 Acceptance criteria'):];m=re.search(r'^- AC-'+str(n)+r'\. (.*?)(?=\n- AC-\d+\.|\n---|\Z)',section,re.M|re.S);assert m
  span=m[0].rstrip();body=re.sub(r'\s+',' ',m[1]).strip()
 if c['label']=='B34 AC-19':
  lines=(p/'brief/sources/b34.md').read_text().splitlines(keepends=True);span=''.join(lines[204:212]);body=re.sub(r'\s+',' ',span).strip()
 parts=clauses(body);assigned=assignments.get(f'B{b} AC-{n}',[])
 if assigned and len(assigned)!=len(parts):draft_findings.append({'parent':c['label'],'review':'manual mixed assignment has different cardinality; inspect all fragments','expected':assigned,'parts':parts})
 atoms=[]
 for i,condition in enumerate(parts,1):
  r=assigned[i-1] if len(assigned)==len(parts) else primary(b,n);bd=boundary(r)
  # Concrete provider/product subcondition remains at its own boundary.
  if b==16 and n in (1,20) and len(parts)==1:bd='cross-cutting'
  if b==33 and n==7:bd='cross-cutting'
  if b==15 and n==27 and ('Квот' in condition or 'grandfather' in condition):bd='cross-cutting'
  if b==18 and n==6 and ('UI ' in condition or 'предпросмотра' in condition):bd='cross-cutting'
  if b==35 and n in (1,8,15):bd='cross-cutting'
  atoms.append({'Atom':c['label']+'.a'+str(i),'Parent AC':c['label'],'R':r,'Boundary':bd,'Owner':rs[r][3],
   'Condition':condition,'Required evidence':f"{c['path']} {c['location']}; original parent SHA256 {c['original_sha256']}; {rs[r][6]}; no executed acceptance in P1"+(f'; full-span SHA256 {hashlib.sha256(span.encode()).hexdigest()}' if span else ''),'Destination':rs[r][7]})
 correction='Historical program-level R links retained; primary atom assignment follows research/01-traceability.md '+','.join(sorted({a['R'] for a in atoms}))+': mechanism/provider/product owner, not blanket acceptance of all original links.'
 if span:correction+=f" Full source-span SHA256 {hashlib.sha256(span.encode()).hexdigest()}; historical fragment hash unchanged."
 if c['label']=='B34 AC-19':correction+=' source-reference to B11 AC-19 and B34 invitation sequence L205–212; not an independent newly declared B34 criterion.'
 records.append({**c,'full_body':body,'full_span':span,'atoms':atoms,'correction':correction})
# Review metadata is explicit; a structural pass never closes this draft.
a=p/'artifacts/B-P1-QUALIFY';(a/'P1.2-draft-model.json').write_text(json.dumps({'status':'draft-semantic-review-required','parents':records,'manual_assignment_review':draft_findings},ensure_ascii=False,indent=2)+'\n')
def cell(x):return str(x).replace('|',r'\|').replace('\n',' ')
def row(xs):return '| '+' | '.join(cell(x) for x in xs)+' |\n'
def tab(cols,rows,chapter,size):
 s=''
 for start in range(0,len(rows),size):
  s+=f'\n### {chapter}.{start//size+1} Source rows {start+1}–{min(start+size,len(rows))}\n\n'
  if start==0:s+=row(cols)+row([':--']*len(cols))
  s+=''.join(row(x) for x in rows[start:start+size])
 return s
text='''# P1 acceptance map — draft under semantic review

Status: draft, P1.2 In progress. Original365parents/hashes/R preserved. This document defines required outcomes; it claims no executed runtime/API/schema/provider/release/live acceptance.

Exact source clauses remain in Condition. Fragment context is the Parent AC/source line/hash in Required evidence. Historical package names, command names, UUID scheme and physical tables are source-profile terms; they are not newly accepted CoreX APIs/DDL. CoreX qualification is limited to the reusable mechanism; concrete policy/provider portions have own owners. Cross-cutting rows need further explicit splitting during semantic review; no GREEN closure from structural count alone.

## Parents
'''
text+=tab(['AC','Original R','Text SHA256','Atoms','Correction'],[[c['label'],c['original_r'],c['original_sha256'],len(c['atoms']),c['correction']] for c in records],1,60)
text+='\n## Atoms\n';atomcols=['Atom','Parent AC','R','Boundary','Owner','Condition','Required evidence','Destination'];atoms=[a for c in records for a in c['atoms']];text+=tab(atomcols,[[a[k] for k in atomcols] for a in atoms],2,25)
text+='\n## Requirements\n';cov=[]
for r,c in rs.items():
 ids=[a['Atom'] for a in atoms if a['R']==r]
 cov.append([r,', '.join(ids) if ids else c[1]+'; exact non-AC program source binding in research/01-traceability.md '+r,c[3],c[7]])
text+=tab(['R','Atoms or non-AC source','Owner','Destination'],cov,3,52)
(p/'findings/P1-acceptance-map.md').write_text(text)
print(json.dumps({'parents':len(records),'atoms':len(atoms),'mixed_cardinality_reviews':len(draft_findings),'status':'draft-semantic-review-required'}))
