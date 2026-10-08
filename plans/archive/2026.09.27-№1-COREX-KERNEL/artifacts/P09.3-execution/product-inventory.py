import pathlib,subprocess,json,hashlib,sys
root=pathlib.Path.cwd();out=pathlib.Path(__file__).resolve().parent
files=subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard','-z']).decode().split('\0')
result={}
for name in sorted(set(files)):
 if not name or name.startswith('plans/'):continue
 p=root/name
 if p.is_file():result[name]=hashlib.sha256(p.read_bytes()).hexdigest()
index=subprocess.check_output(['git','ls-files','--stage','-z']);doc={'files':result,'index_sha256':hashlib.sha256(index).hexdigest()}
(out/f'{sys.argv[1]}.json').write_text(json.dumps(doc,indent=2)+'\n');print('inventory',sys.argv[1],len(result))
if len(sys.argv)>2:
 before=json.loads((out/f'{sys.argv[2]}.json').read_text());assert before==doc,'mutation changed source/index inventory'
 print('source and index restored exactly')
