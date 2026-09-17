exec(open('/tmp/corex-p2-common.py').read())
s=importlib.util.spec_from_file_location('check',p/'scripts/task-check.py');m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
f=p/'artifacts/design-finish.md';body=f.read_text();heading='## D21 finite execution declaration correction'
if heading in body:
 before,after=body.split(heading,1);foot=after.split(heading,1)[0];body=before+heading+foot
names=sorted(m.design_inputs(p));new='## Design inputs\n\n| Path | SHA256 |\n| :-- | :-- |\n'+''.join('| '+n+' | '+sha(p/n)+' |\n' for n in names)+'\n';body=re.sub(r'^## Design inputs\n.*?(?=^## |\Z)',lambda _:new,body,flags=re.M|re.S);f.write_text(body);print('Resealed',len(names),'inputs; D21 footer unique')
