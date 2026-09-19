from pathlib import Path
import importlib.util,json,hashlib,re
p=Path('plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING');a=p/'artifacts/B-P2-PROPOSALS'
s=importlib.util.spec_from_file_location('vp1',p/'scripts/validate-p1.py');v=importlib.util.module_from_spec(s);s.loader.exec_module(v)
def tbl(name,heading,cols):return v.table(p/name,heading,tuple(cols.split(' / ')))
def mdtable(heading,cols,rows):
 keys=cols.split(' / ');return '## '+heading+'\n\n| '+' | '.join(keys)+' |\n| '+' | '.join(':--' for _ in keys)+' |\n'+''.join('| '+' | '.join(str(r[k]).replace('|','\\|').replace('\n',' ') for k in keys)+' |\n' for r in rows)+'\n'
lock=tbl('findings/P1-requirements-lock.md','Requirements','R / Disposition / Owner / Evidence or gap / Resume / Destination')
ledger=tbl('findings/P1-evidence-ledger.md','Requirements','R / Disposition / Owner / Source anchor / Equivalent behavior / Tests and receipt class / Evidence or missing receipt / Gap owner / Resume / Destination');ld={r['R']:r for r in ledger}
maprows=tbl('findings/P1-acceptance-map.md','Requirements','R / Atoms or non-AC source / Owner / Destination');mr={r['R']:r for r in maprows}
gates=tbl('findings/P1-evidence-ledger.md','Gates','ID / R / Owner / Required receipt / Resume')
def rgates(r):return ', '.join(x['ID'] for x in gates if r in re.findall(r'R\d{2}',x['R']))
def sha(path):return hashlib.sha256(Path(path).read_bytes()).hexdigest()
